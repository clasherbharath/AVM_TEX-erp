<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/stock_movement.php';
require_once __DIR__ . '/../helpers/audit.php';

requireAdminRole('/billing/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

requireValidCsrfToken('/billing/index.php');

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid invoice.';
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT invoice_number FROM invoices WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Invoice not found.';
        header('Location: ' . APP_BASE . '/billing/index.php');
        exit;
    }

    $items = $pdo->prepare('SELECT product_id, quantity FROM invoice_items WHERE invoice_id = :id');
    $items->execute([':id' => $id]);
    $lines = $items->fetchAll(PDO::FETCH_ASSOC);

    $qtyByProduct = [];
    foreach ($lines as $line) {
        $productId = (int)($line['product_id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }
        $qtyByProduct[$productId] = ($qtyByProduct[$productId] ?? 0.0) + (float)($line['quantity'] ?? 0);
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

    $restore = $pdo->prepare('UPDATE inventory SET quantity = quantity + :qty WHERE id = :id');
    foreach ($qtyByProduct as $productId => $restoreQty) {
        $stock = $lockedInventory[$productId] ?? null;
        if (!$stock) {
            throw new RuntimeException('Inventory item for invoice restore not found.');
        }

        $currentQty = (float)$stock['quantity'];
        $newQty = round($currentQty + $restoreQty, 2);

        $restore->execute([
            ':qty' => $restoreQty,
            ':id' => $productId,
        ]);

        recordStockMovement(
            $pdo,
            'delete',
            $productId,
            $currentQty,
            $newQty,
            $restoreQty,
            'invoice',
            $id,
            'Invoice ' . ($invoice['invoice_number'] ?? '') . ' deleted and stock restored'
        );
    }

    $deleteTransactions = $pdo->prepare('DELETE FROM transactions WHERE invoice_id = :id');
    $deleteTransactions->execute([':id' => $id]);

    $del = $pdo->prepare('DELETE FROM invoices WHERE id = :id');
    $del->execute([':id' => $id]);

    $pdo->commit();

    // Audit invoice deletion
    logAudit($pdo, $_SESSION['admin_id'] ?? null, 'invoice_delete', 'invoices', $id, 'Invoice ' . ($invoice['invoice_number'] ?? '') . ' deleted');

    $_SESSION['flash_success'] = 'Invoice ' . $invoice['invoice_number'] . ' deleted and stock restored.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete invoice.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete invoice.';
}

header('Location: ' . APP_BASE . '/billing/index.php');
exit;
