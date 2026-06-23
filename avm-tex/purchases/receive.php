<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/procurement.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

requireValidCsrfToken('/purchases/index.php');

$purchaseOrderId = (int)($_POST['purchase_order_id'] ?? 0);
$received = $_POST['received'] ?? [];

if ($purchaseOrderId <= 0 || !is_array($received)) {
    $_SESSION['flash_error'] = 'Invalid receipt payload.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $order = fetchPurchaseOrderDetails($pdo, $purchaseOrderId);
    if (!$order) {
        throw new RuntimeException('Purchase order not found.');
    }
    if ($order['status'] === 'cancelled') {
        throw new RuntimeException('Cancelled purchase orders cannot receive stock.');
    }

    $itemStmt = $pdo->prepare('SELECT * FROM purchase_items WHERE id = :id AND purchase_order_id = :purchase_order_id LIMIT 1 FOR UPDATE');
    $updateItem = $pdo->prepare('UPDATE purchase_items SET received_quantity = received_quantity + :qty WHERE id = :id');
    $updateInventory = $pdo->prepare('UPDATE inventory SET quantity = quantity + :qty WHERE id = :product_id');
    $inventoryLock = $pdo->prepare('SELECT quantity, product_name, unit FROM inventory WHERE id = :id LIMIT 1 FOR UPDATE');

    foreach ($received as $itemId => $qtyRaw) {
        $qty = (float)$qtyRaw;
        $itemId = (int)$itemId;
        if ($qty <= 0 || $itemId <= 0) {
            continue;
        }

        $itemStmt->execute([':id' => $itemId, ':purchase_order_id' => $purchaseOrderId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException('Purchase item not found.');
        }

        $remaining = max(0, (float)$item['quantity'] - (float)$item['received_quantity']);
        if ($qty > $remaining + 0.01) {
            throw new RuntimeException('Received quantity exceeds remaining balance for ' . $item['product_name_snapshot'] . '.');
        }

        $inventoryLock->execute([':id' => (int)$item['product_id']]);
        $inventory = $inventoryLock->fetch(PDO::FETCH_ASSOC);
        if (!$inventory) {
            throw new RuntimeException('Inventory item not found for received product.');
        }

        $before = (float)$inventory['quantity'];
        $after = $before + $qty;

        $updateInventory->execute([
            ':qty' => $qty,
            ':product_id' => (int)$item['product_id'],
        ]);
        $updateItem->execute([
            ':qty' => $qty,
            ':id' => $itemId,
        ]);

        recordStockMovement(
            $pdo,
            'purchase',
            (int)$item['product_id'],
            $before,
            $after,
            $qty,
            'purchase_order',
            $purchaseOrderId,
            'Received against PO ' . $order['po_number']
        );
    }

    syncPurchaseOrderState($pdo, $purchaseOrderId);
    $pdo->commit();

    $_SESSION['flash_success'] = 'Goods receipt posted successfully.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not post goods receipt.';
}

header('Location: ' . APP_BASE . '/purchases/view.php?id=' . $purchaseOrderId);
exit;
