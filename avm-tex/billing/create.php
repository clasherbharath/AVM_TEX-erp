<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/invoice_helper.php';

$pageTitle = 'Create Invoice • A.V.M TEX ERP';
$activeMenu = 'Billing';

$customerList = [];
$productList = [];
$dbError = '';
$invoiceNumber = 'INV-' . date('Ymd') . '-0001';

try {
    $customerList = $pdo->query(
        'SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $productList = $pdo->query(
        'SELECT id, product_name, quantity, unit, selling_price, gst_percentage
         FROM inventory ORDER BY product_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $invoiceNumber = generateInvoiceNumber($pdo);
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load billing data. Import sql/invoices.sql and sql/invoice_items.sql';
    $invoiceNumber = 'INV-' . date('Ymd') . '-0001';
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
                    <h2 class="mb-1 fw-bold">Create Invoice</h2>
                    <div class="avm-muted">Bill customers and auto-update inventory stock.</div>
                </div>
                <a href="<?= APP_BASE ?>/billing/index.php" class="btn btn-outline-secondary btn-sm">← Billing</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php elseif ($customerList === []): ?>
                <div class="alert alert-warning">Please create a customer before creating an invoice. <a href="<?= APP_BASE ?>/customers/add.php">Add a customer</a>.</div>
            <?php elseif ($productList === []): ?>
                <div class="alert alert-warning">No inventory items found. <a href="<?= APP_BASE ?>/inventory/add.php">Add inventory</a> first.</div>
            <?php else: ?>
                <div class="card avm-card">
                    <div class="card-body">
                        <?php require __DIR__ . '/_invoice_form.php'; ?>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php if ($dbError === '' && $customerList !== [] && $productList !== []): ?>
<script src="<?= APP_BASE ?>/assets/js/billing.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
