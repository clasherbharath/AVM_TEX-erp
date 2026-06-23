<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/purchase_validation.php';
require_once __DIR__ . '/../helpers/procurement.php';

$pageTitle = 'Edit Purchase Order • A.V.M TEX ERP';
$activeMenu = 'Purchases';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid purchase order selected.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

$order = fetchPurchaseOrderDetails($pdo, $id);
if (!$order) {
    $_SESSION['flash_error'] = 'Purchase order not found.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

$settlementSummary = getPurchaseOrderSettlementSummary($pdo, $id);
$editBlocked = $settlementSummary !== null && (
    (float)($settlementSummary['received_quantity'] ?? 0) > 0.01
    || (float)($settlementSummary['paid_total'] ?? 0) > 0.01
);
$editBlockedMessage = 'This purchase order already has received stock or supplier payments, so it cannot be edited. Create a new adjustment or correction entry instead.';

try {
    $suppliers = $pdo->query('SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $products = $pdo->query('SELECT id, product_name, purchase_price, selling_price, gst_percentage, quantity, unit FROM inventory ORDER BY product_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $suppliers = [];
    $products = [];
    $errors['general'] = APP_DEBUG ? $e->getMessage() : 'Could not load procurement reference data.';
}

$form = purchaseOrderFormFromSource($order);
$purchaseRows = [];
foreach ($order['items'] as $item) {
    $purchaseRows[] = [
        'product_id' => (int)$item['product_id'],
        'quantity' => (float)$item['quantity'],
        'purchase_price' => (float)$item['purchase_price'],
        'gst_percentage' => (float)$item['gst_percentage'],
        'selling_price_snapshot' => (float)$item['selling_price_snapshot'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($editBlocked) {
        $_SESSION['flash_error'] = $editBlockedMessage;
        header('Location: ' . APP_BASE . '/purchases/view.php?id=' . $id);
        exit;
    }

    require_once __DIR__ . '/../includes/security.php';
    requireValidCsrfToken('/purchases/edit.php?id=' . $id);

    $form = purchaseOrderFormFromSource($_POST);
    $errors = validatePurchaseOrderInput($pdo, $_POST);
    $purchaseRows = extractPurchaseRows($_POST);

    if ($errors === []) {
        $normalized = normalizePurchaseOrderInput($_POST);
        $enrichedRows = [];

        try {
            foreach ($normalized['rows'] as $row) {
                $stmt = $pdo->prepare('SELECT id, product_name, purchase_price, selling_price, gst_percentage FROM inventory WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int)$row['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new RuntimeException('Selected product not found.');
                }
                $row['product_name'] = (string)$product['product_name'];
                $enrichedRows[] = $row;
            }

            $totals = calculatePurchaseTotals($enrichedRows, (float)$normalized['discount']);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE purchase_orders SET
                    supplier_id = :supplier_id,
                    order_date = :order_date,
                    expected_date = :expected_date,
                    status = :status,
                    subtotal = :subtotal,
                    discount = :discount,
                    gst_total = :gst_total,
                    grand_total = :grand_total,
                    notes = :notes
                 WHERE id = :id'
            );
            $stmt->execute([
                ':supplier_id' => (int)$normalized['supplier_id'],
                ':order_date' => $normalized['order_date'],
                ':expected_date' => $normalized['expected_date'],
                ':status' => $normalized['status'],
                ':subtotal' => $totals['subtotal'],
                ':discount' => (float)$normalized['discount'],
                ':gst_total' => $totals['gst_total'],
                ':grand_total' => $totals['grand_total'],
                ':notes' => $normalized['notes'],
                ':id' => $id,
            ]);

            $pdo->prepare('DELETE FROM purchase_items WHERE purchase_order_id = :id')->execute([':id' => $id]);
            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_items (
                    purchase_order_id, product_id, product_name_snapshot, quantity,
                    received_quantity, purchase_price, selling_price_snapshot,
                    gst_percentage, line_subtotal, line_gst, line_total
                 ) VALUES (
                    :purchase_order_id, :product_id, :product_name_snapshot, :quantity,
                    :received_quantity, :purchase_price, :selling_price_snapshot,
                    :gst_percentage, :line_subtotal, :line_gst, :line_total
                 )'
            );
            foreach ($totals['lines'] as $line) {
                $itemStmt->execute([
                    ':purchase_order_id' => $id,
                    ':product_id' => $line['product_id'],
                    ':product_name_snapshot' => $line['product_name'],
                    ':quantity' => $line['quantity'],
                    ':received_quantity' => 0,
                    ':purchase_price' => $line['purchase_price'],
                    ':selling_price_snapshot' => $line['selling_price_snapshot'],
                    ':gst_percentage' => $line['gst_percentage'],
                    ':line_subtotal' => $line['line_subtotal'],
                    ':line_gst' => $line['line_gst'],
                    ':line_total' => $line['line_total'],
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Purchase order updated successfully.';
            header('Location: ' . APP_BASE . '/purchases/view.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG ? $e->getMessage() : 'Failed to update purchase order.';
        }
    }
}

if ($editBlocked) {
    $errors['general'] = $editBlockedMessage;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid"><div class="row">
    <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
    <main class="col-12 col-lg-9 col-xl-10 avm-content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="mb-1 fw-bold">Edit Purchase Order</h2>
                <div class="avm-muted">Purchase #<?= htmlspecialchars($order['po_number']) ?></div>
            </div>
            <a href="<?= APP_BASE ?>/purchases/view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
        <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
        <?php if (!empty($errors['general'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>
        <?php if ($editBlocked): ?>
            <div class="card avm-card">
                <div class="card-body">
                    <p class="mb-0">You can view this purchase order, but editing is disabled because receipt or payment history already exists.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card avm-card"><div class="card-body"><?php require __DIR__ . '/_form.php'; ?></div></div>
        <?php endif; ?>
    </main>
</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
