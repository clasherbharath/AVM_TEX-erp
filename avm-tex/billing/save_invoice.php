<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/billing_validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}

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

$customerCheck = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
$customerCheck->execute([':id' => $validation['customer_id']]);
if (!$customerCheck->fetch()) {
    $_SESSION['flash_error'] = 'Selected customer does not exist. Please choose an existing customer before saving the invoice.';
    $_SESSION['invoice_form_old'] = $_POST;
    header('Location: ' . APP_BASE . '/billing/create.php');
    exit;
}

try {
    $pdo->beginTransaction();

    foreach ($totals['lines'] as $line) {
        $lock = $pdo->prepare('SELECT product_name, quantity FROM inventory WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $line['product_id']]);
        $stock = $lock->fetch(PDO::FETCH_ASSOC);

        if (!$stock || (float)$stock['quantity'] < $line['quantity']) {
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

    $insertedRows = 0;
    $productIds = array_values(is_array($_POST['product_id'] ?? []) ? $_POST['product_id'] : []);

    foreach ($productIds as $idx => $productId) {
        $line = $totals['lines'][$idx] ?? null;
        if (!is_array($line)) {
            continue;
        }

        $quantity = (float)$line['quantity'];
        $price = (float)$line['price'];
        $gst = (float)$line['gst_percentage'];
        $subtotal = round($quantity * $price, 2);
        $gstAmount = round($subtotal * $gst / 100, 2);
        $total = round($subtotal + $gstAmount, 2);

        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<pre>';
            print_r([
                'invoice_id' => $invoiceId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'gst' => $gst,
                'total' => $total,
            ]);
            echo '</pre>';
        }

        $itemStmt->execute([
            ':invoice_id' => $invoiceId,
            ':product_id' => $line['product_id'],
            ':quantity' => $quantity,
            ':price' => $price,
            ':gst_percentage' => $gst,
            ':total' => $total,
        ]);
        $insertedRows++;

        $stockStmt->execute([
            ':qty' => $quantity,
            ':id' => $line['product_id'],
        ]);
    }

    // Auto-create transaction if invoice status is 'paid'
    if ($validation['status'] === 'paid') {
        $dupCheck = $pdo->prepare(
            'SELECT COUNT(*) as count FROM transactions WHERE invoice_id = :invoice_id'
        );
        $dupCheck->execute([':invoice_id' => $invoiceId]);
        $dupResult = $dupCheck->fetch(PDO::FETCH_ASSOC);

        if ((int)($dupResult['count'] ?? 0) === 0) {
            $transStmt = $pdo->prepare(
                'INSERT INTO transactions (
                    transaction_type, invoice_id, reference_number, transaction_date,
                    amount, payment_method, transaction_notes
                ) VALUES (
                    :transaction_type, :invoice_id, :reference_number, :transaction_date,
                    :amount, :payment_method, :transaction_notes
                )'
            );
            $transStmt->execute([
                ':transaction_type' => 'payment',
                ':invoice_id' => $invoiceId,
                ':reference_number' => 'AUTO-' . $invoiceNumber,
                ':transaction_date' => $validation['invoice_date'],
                ':amount' => $totals['grand_total'],
                ':payment_method' => 'cash',
                ':transaction_notes' => 'Auto-generated from paid invoice',
            ]);
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
