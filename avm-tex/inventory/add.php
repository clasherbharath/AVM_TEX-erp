<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

$pageTitle = 'Add Inventory • A.V.M TEX ERP';
$activeMenu = 'Inventory';

$errors = [];
$form = emptyInventoryForm();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/inventory/add.php');

    $form = inventoryFormFromSource($_POST);
    $errors = validateInventoryInput($form);

    if ($errors === []) {
        $data = normalizeInventoryInput($form);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO inventory (
                    product_name, category, quantity, min_stock, unit,
                    purchase_price, selling_price, supplier,
                    gst_percentage, barcode
                ) VALUES (
                    :product_name, :category, :quantity, :min_stock, :unit,
                    :purchase_price, :selling_price, :supplier,
                    :gst_percentage, :barcode
                )'
            );
            $stmt->execute([
                ':product_name' => $data['product_name'],
                ':category' => $data['category'],
                ':quantity' => $data['quantity'],
                ':min_stock' => $data['min_stock'],
                ':unit' => $data['unit'],
                ':purchase_price' => $data['purchase_price'],
                ':selling_price' => $data['selling_price'],
                ':supplier' => $data['supplier'],
                ':gst_percentage' => $data['gst_percentage'],
                ':barcode' => $data['barcode'],
            ]);

            $itemId = (int)$pdo->lastInsertId();
            recordStockMovement(
                $pdo,
                'initial',
                $itemId,
                0.0,
                (float)$data['quantity'],
                (float)$data['quantity'],
                'inventory',
                $itemId,
                'Initial stock recorded when item was created'
            );

            $pdo->commit();

            $_SESSION['flash_success'] = 'Inventory item added successfully.';
            header('Location: ' . APP_BASE . '/inventory/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to save inventory item.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? $e->getMessage()
                : 'Failed to save inventory item.';
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
        <h2 class="mb-1 fw-bold">Add Inventory Item</h2>
        <div class="avm-muted">Register a new product in stock.</div>
    </div>
    <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>

<div class="card avm-card">
    <div class="card-body"><?php require __DIR__ . '/_form.php'; ?></div>
</div>

        </main>
    </div>
</div>

<style>@media (min-width:992px){#avmSidebar.offcanvas-lg{position:fixed;top:56px;bottom:0;left:0;transform:none;visibility:visible!important;}}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
