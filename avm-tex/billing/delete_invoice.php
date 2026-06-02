<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

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

    $restore = $pdo->prepare('UPDATE inventory SET quantity = quantity + :qty WHERE id = :id');
    foreach ($lines as $line) {
        $restore->execute([
            ':qty' => $line['quantity'],
            ':id' => $line['product_id'],
        ]);
    }

    $del = $pdo->prepare('DELETE FROM invoices WHERE id = :id');
    $del->execute([':id' => $id]);

    $pdo->commit();
    $_SESSION['flash_success'] = 'Invoice ' . $invoice['invoice_number'] . ' deleted and stock restored.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete invoice.';
}

header('Location: ' . APP_BASE . '/billing/index.php');
exit;
