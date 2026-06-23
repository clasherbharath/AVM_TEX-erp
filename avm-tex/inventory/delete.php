<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

requireAdminRole('/inventory/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

requireValidCsrfToken('/inventory/index.php');

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid inventory item.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$usageStmt = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM invoice_items WHERE product_id = :invoice_id) AS invoice_usage,
        (SELECT COUNT(*) FROM purchase_items WHERE product_id = :purchase_id) AS purchase_usage'
);
$usageStmt->execute([
    ':invoice_id' => $id,
    ':purchase_id' => $id,
]);
$usage = $usageStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ((int)($usage['invoice_usage'] ?? 0) > 0 || (int)($usage['purchase_usage'] ?? 0) > 0) {
    $_SESSION['flash_error'] = 'This inventory item has invoice or purchase history and cannot be deleted. Please archive it instead.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT product_name, quantity FROM inventory WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Item not found or already deleted.';
    } else {
        $currentQty = (float)$row['quantity'];
        $del = $pdo->prepare('DELETE FROM inventory WHERE id = :id');
        $del->execute([':id' => $id]);

        recordStockMovement(
            $pdo,
            'delete',
            $id,
            $currentQty,
            0.0,
            -$currentQty,
            'inventory_delete',
            $id,
            'Inventory item deleted from stock list'
        );

        $pdo->commit();
        $_SESSION['flash_success'] = 'Deleted "' . $row['product_name'] . '" from inventory.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG
        ? $e->getMessage()
        : 'Could not delete inventory item.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG
        ? $e->getMessage()
        : 'Could not delete inventory item.';
}

header('Location: ' . APP_BASE . '/inventory/index.php');
exit;
