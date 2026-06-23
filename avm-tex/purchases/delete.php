<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

requireAdminRole('/purchases/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

requireValidCsrfToken('/purchases/index.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid purchase order selected.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $summary = getPurchaseOrderSettlementSummary($pdo, $id);
    if (!$summary) {
        throw new RuntimeException('Purchase order not found.');
    }
    if ((float)$summary['received_quantity'] > 0 || (float)$summary['paid_total'] > 0) {
        throw new RuntimeException('Cannot delete a purchase order with receipts or payments.');
    }

    $delItems = $pdo->prepare('DELETE FROM purchase_items WHERE purchase_order_id = :id');
    $delItems->execute([':id' => $id]);
    $delOrder = $pdo->prepare('DELETE FROM purchase_orders WHERE id = :id');
    $delOrder->execute([':id' => $id]);

    $pdo->commit();
    $_SESSION['flash_success'] = 'Purchase order deleted successfully.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete purchase order.';
}

header('Location: ' . APP_BASE . '/purchases/index.php');
exit;
