<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/purchase_validation.php';
require_once __DIR__ . '/../helpers/procurement.php';

$pageTitle = 'New Purchase Order • A.V.M TEX ERP';
$activeMenu = 'Purchases';

$errors = [];
$form = emptyPurchaseOrderForm();
$purchaseRows = [];

try {
    $suppliers = $pdo->query('SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $products = $pdo->query('SELECT id, product_name, purchase_price, selling_price, gst_percentage, quantity, unit FROM inventory ORDER BY product_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $suppliers = [];
    $products = [];
    $errors['general'] = APP_DEBUG ? $e->getMessage() : 'Could not load procurement reference data.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/security.php';
    requireValidCsrfToken('/purchases/add.php');

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
                if ((float)$row['selling_price_snapshot'] <= 0) {
                    $row['selling_price_snapshot'] = (float)$product['selling_price'];
                }
                if ((float)$row['purchase_price'] <= 0) {
                    $row['purchase_price'] = (float)$product['purchase_price'];
                }
                if ((float)$row['gst_percentage'] <= 0) {
                    $row['gst_percentage'] = (float)$product['gst_percentage'];
                }
                $enrichedRows[] = $row;
            }

            $totals = calculatePurchaseTotals($enrichedRows, (float)$normalized['discount']);
            $purchaseNumber = generatePurchaseOrderNumber($pdo);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders (
                    po_number, supplier_id, order_date, expected_date, status, payment_status,
                    subtotal, discount, gst_total, grand_total, notes, created_by
                 ) VALUES (
                    :po_number, :supplier_id, :order_date, :expected_date, :status, :payment_status,
                    :subtotal, :discount, :gst_total, :grand_total, :notes, :created_by
                 )'
            );
            $stmt->execute([
                ':po_number' => $purchaseNumber,
                ':supplier_id' => (int)$normalized['supplier_id'],
                ':order_date' => $normalized['order_date'],
                ':expected_date' => $normalized['expected_date'],
                ':status' => $normalized['status'],
                ':payment_status' => 'unpaid',
                ':subtotal' => $totals['subtotal'],
                ':discount' => (float)$normalized['discount'],
                ':gst_total' => $totals['gst_total'],
                ':grand_total' => $totals['grand_total'],
                ':notes' => $normalized['notes'],
                ':created_by' => $_SESSION['admin_id'] ?? null,
            ]);

            $purchaseOrderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_items (
                    purchase_order_id, product_id, product_name_snapshot, quantity,
                    received_quantity, purchase_price, selling_price_snapshot,
                    gst_percentage, line_subtotal, line_gst, line_total
                 ) VALUES (
                    :purchase_order_id, :product_id, :product_name_snapshot, :quantity,
                    0, :purchase_price, :selling_price_snapshot,
                    :gst_percentage, :line_subtotal, :line_gst, :line_total
                 )'
            );

            foreach ($totals['lines'] as $line) {
                $itemStmt->execute([
                    ':purchase_order_id' => $purchaseOrderId,
                    ':product_id' => $line['product_id'],
                    ':product_name_snapshot' => $line['product_name'],
                    ':quantity' => $line['quantity'],
                    ':purchase_price' => $line['purchase_price'],
                    ':selling_price_snapshot' => $line['selling_price_snapshot'],
                    ':gst_percentage' => $line['gst_percentage'],
                    ':line_subtotal' => $line['line_subtotal'],
                    ':line_gst' => $line['line_gst'],
                    ':line_total' => $line['line_total'],
                ]);
            }

            $pdo->commit();

            $_SESSION['flash_success'] = 'Purchase order created successfully.';
            header('Location: ' . APP_BASE . '/purchases/view.php?id=' . $purchaseOrderId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG ? $e->getMessage() : 'Failed to save purchase order.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Create Purchase Order</h2>
                    <div class="avm-muted">Record purchases from suppliers and keep stock and margin data aligned.</div>
                </div>
                <a href="<?= APP_BASE ?>/purchases/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if (!empty($errors['general'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>

            <div class="card avm-card"><div class="card-body">
                <?php require __DIR__ . '/_form.php'; ?>
            </div></div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
