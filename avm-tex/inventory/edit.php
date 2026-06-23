<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

$pageTitle = 'Edit Inventory • A.V.M TEX ERP';
$activeMenu = 'Inventory';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid inventory item.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, product_name, category, quantity, min_stock, unit, purchase_price, selling_price,
            supplier, gst_percentage, barcode, created_at, updated_at
     FROM inventory WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['flash_error'] = 'Inventory item not found.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$form = inventoryFormFromSource($item);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/inventory/edit.php?id=' . $id);

    $form = inventoryFormFromSource($_POST);
    $errors = validateInventoryInput($form);

    if ($errors === []) {
        $data = normalizeInventoryInput($form);

        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare(
                'UPDATE inventory SET
                    product_name = :product_name,
                    category = :category,
                    quantity = :quantity,
                    min_stock = :min_stock,
                    unit = :unit,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    supplier = :supplier,
                    gst_percentage = :gst_percentage,
                    barcode = :barcode
                 WHERE id = :id'
            );
            $update->execute([
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
                ':id' => $id,
            ]);

            $previousQty = (float)$item['quantity'];
            $newQty = (float)$data['quantity'];
            if (abs($newQty - $previousQty) > 0.00001) {
                recordStockMovement(
                    $pdo,
                    'adjustment',
                    $id,
                    $previousQty,
                    $newQty,
                    round($newQty - $previousQty, 2),
                    'inventory_edit',
                    $id,
                    'Inventory item updated via edit form'
                );
            }

            $pdo->commit();

            $_SESSION['flash_success'] = 'Inventory item updated successfully.';
            header('Location: ' . APP_BASE . '/inventory/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to update inventory item.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? $e->getMessage()
                : 'Failed to update inventory item.';
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
        <h2 class="mb-1 fw-bold">Edit Inventory Item</h2>
        <div class="avm-muted">Current stock: <?= number_format((float)$item['quantity'], 2) ?> <?= htmlspecialchars($item['unit']) ?></div>
    </div>
    <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>

<div class="card avm-card mb-3">
    <div class="card-body">
        <?php
        $formAction = APP_BASE . '/inventory/edit.php';
        $submitLabel = 'Update Item';
        $hiddenId = $id;
        require __DIR__ . '/_form.php';
        ?>
    </div>
</div>

        </main>
    </div>
</div>

<style>@media (min-width:992px){#avmSidebar.offcanvas-lg{position:fixed;top:56px;bottom:0;left:0;transform:none;visibility:visible!important;}}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
