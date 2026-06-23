<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/billing_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/transaction_schema.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}

requireValidCsrfToken('/billing/create.php', 'Your session expired while saving the invoice. Please try again.');

$items = [
    'product_id' => $_POST['product_id'] ?? [],
    'quantity' => $_POST['quantity'] ?? [],
    'price' => $_POST['price'] ?? [],
    'gst' => $_POST['gst'] ?? [],
];

$validation = validateInvoicePost($pdo, $_POST);

if ($validation['errors'] !== []) {
    $_SESSION['flash_error'] = implode(' ', $validation['errors']);
    $_SESSION['invoice_form_old'] = $_POST;
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}

$totals = calculateInvoiceTotals($validation['rows'], $validation['discount']);

if ($totals['lines'] === []) {
    $_SESSION['flash_error'] = 'No valid invoice line items.';
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}

$invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
if ($invoiceNumber === '') {
    $invoiceNumber = generateInvoiceNumber($pdo);
}

try {
    $pdo->beginTransaction();

    $qtyByProduct = [];
    foreach ($totals['lines'] as $line) {
        $productId = (int)$line['product_id'];
        $qtyByProduct[$productId] = ($qtyByProduct[$productId] ?? 0.0) + (float)$line['quantity'];
    }

    $lockedInventory = [];
    if ($qtyByProduct !== []) {
        $placeholders = [];
        $lockParams = [];
        foreach (array_values(array_keys($qtyByProduct)) as $index => $productId) {
            $placeholder = ':product_' . $index;
            $placeholders[] = $placeholder;
            $lockParams[$placeholder] = $productId;
        }

        $lock = $pdo->prepare(
            'SELECT id, product_name, quantity FROM inventory WHERE id IN (' . implode(', ', $placeholders) . ') FOR UPDATE'
        );
        $lock->execute($lockParams);

        foreach ($lock->fetchAll(PDO::FETCH_ASSOC) as $stockRow) {
            $lockedInventory[(int)$stockRow['id']] = $stockRow;
        }
    }

    $currentStockByProduct = $lockedInventory;

    foreach ($qtyByProduct as $productId => $neededQty) {
        $stock = $lockedInventory[$productId] ?? null;
        if (!$stock || (float)$stock['quantity'] < $neededQty) {
            throw new RuntimeException(
                'Insufficient stock for ' . ($stock['product_name'] ?? 'product') . '.'
            );
        }
    }

    $invStmt = $pdo->prepare(
        'INSERT INTO invoices (
            invoice_number, customer_id, invoice_date, subtotal,
            discount, gst_total, grand_total, status, notes
        ) VALUES (
            :invoice_number, :customer_id, :invoice_date, :subtotal,
            :discount, :gst_total, :grand_total, :status, :notes
        )'
    );
    $invStmt->execute([
        ':invoice_number' => $invoiceNumber,
        ':customer_id' => $validation['customer_id'],
        ':invoice_date' => $validation['invoice_date'],
        ':subtotal' => $totals['subtotal'],
        ':discount' => $validation['discount'],
        ':gst_total' => $totals['gst_total'],
        ':grand_total' => $totals['grand_total'],
        ':status' => $validation['status'],
        ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
    ]);

    $invoiceId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO invoice_items (invoice_id, product_id, quantity, price, gst_percentage, total)
         VALUES (:invoice_id, :product_id, :quantity, :price, :gst_percentage, :total)'
    );

    $stockStmt = $pdo->prepare(
        'UPDATE inventory SET quantity = quantity - :qty WHERE id = :id'
    );

    foreach ($totals['lines'] as $line) {
        if (!is_array($line)) {
            continue;
        }

        $quantity = (float)$line['quantity'];
        $price = (float)$line['price'];
        $gst = (float)$line['gst_percentage'];
        $subtotal = round($quantity * $price, 2);
        $gstAmount = round($subtotal * $gst / 100, 2);
        $total = round($subtotal + $gstAmount, 2);

        $itemStmt->execute([
            ':invoice_id' => $invoiceId,
            ':product_id' => $line['product_id'],
            ':quantity' => $quantity,
            ':price' => $price,
            ':gst_percentage' => $gst,
            ':total' => $total,
        ]);

        $stockStmt->execute([
            ':qty' => $quantity,
            ':id' => $line['product_id'],
        ]);

        $productId = (int)$line['product_id'];
        $currentQty = (float)($currentStockByProduct[$productId]['quantity'] ?? 0);
        $newQty = round($currentQty - $quantity, 2);

        recordStockMovement(
            $pdo,
            'sale',
            $productId,
            $currentQty,
            $newQty,
            -$quantity,
            'invoice',
            $invoiceId,
            'Invoice ' . $invoiceNumber . ' created'
        );

        $currentStockByProduct[$productId]['quantity'] = $newQty;
    }

    // Auto-create transaction if invoice status is 'paid'
    if ($validation['status'] === 'paid') {
        $transactionSchema = getTransactionSchema($pdo);
        $dupCheck = $pdo->prepare(
            'SELECT COUNT(*) as count FROM transactions WHERE invoice_id = :invoice_id'
        );
        $dupCheck->execute([':invoice_id' => $invoiceId]);
        $dupResult = $dupCheck->fetch(PDO::FETCH_ASSOC);

        if ((int)($dupResult['count'] ?? 0) === 0) {
            $write = buildTransactionWriteSet($transactionSchema, [
                'transaction_type' => 'payment',
                'invoice_id' => $invoiceId,
                'customer_id' => $validation['customer_id'],
                'reference_number' => 'AUTO-' . $invoiceNumber,
                'transaction_date' => $validation['invoice_date'],
                'amount' => $totals['grand_total'],
                'payment_method' => 'cash',
                'bank_name' => null,
                'cheque_number' => null,
                'transaction_notes' => 'Auto-generated from paid invoice',
                'recorded_by' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            ]);

            $transStmt = $pdo->prepare(
                'INSERT INTO transactions (' . implode(', ', $write['columns']) . ')
                 VALUES (:' . implode(', :', $write['columns']) . ')'
            );
            $transStmt->execute($write['params']);
        }
    }

    $pdo->commit();

    unset($_SESSION['invoice_form_old']);
    $_SESSION['flash_success'] = 'Invoice ' . $invoiceNumber . ' saved successfully.';
    header('Location: invoice_view.php?id=' . $invoiceId);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG
        ? 'Database error: ' . $e->getMessage()
        : 'Failed to save invoice. Please verify the selected customer and try again.';
    $_SESSION['invoice_form_old'] = $_POST;
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : (APP_DEBUG ? $e->getMessage() : 'Failed to save invoice. Please try again.');
    $_SESSION['invoice_form_old'] = $_POST;
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}
