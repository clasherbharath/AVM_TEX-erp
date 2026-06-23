<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/stock_movement.php';

$pageTitle = 'Update Stock • A.V.M TEX ERP';
$activeMenu = 'Inventory';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid inventory item.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, product_name, quantity, min_stock, unit, category FROM inventory WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['flash_error'] = 'Inventory item not found.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$currentQty = (float)$item['quantity'];
$form = [
    'stock_action' => (string)($_POST['stock_action'] ?? 'add'),
    'adjust_qty' => (string)($_POST['adjust_qty'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/inventory/stock_update.php?id=' . $id);

    $errors = validateStockAdjustment($form, $currentQty);

    if ($errors === []) {
        $newQty = applyStockAdjustment($currentQty, $form['stock_action'], (float)$form['adjust_qty']);

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare('UPDATE inventory SET quantity = :quantity WHERE id = :id');
            $upd->execute([':quantity' => $newQty, ':id' => $id]);

            recordStockMovement(
                $pdo,
                'adjustment',
                $id,
                $currentQty,
                $newQty,
                round($newQty - $currentQty, 2),
                'stock_update',
                $id,
                'Manual stock ' . $form['stock_action'] . ' operation'
            );

            $pdo->commit();

            $_SESSION['flash_success'] = sprintf(
                'Stock updated for "%s": %s → %s %s',
                $item['product_name'],
                number_format($currentQty, 2),
                number_format($newQty, 2),
                $item['unit']
            );
            header('Location: ' . APP_BASE . '/inventory/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to update stock.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? $e->getMessage()
                : 'Failed to update stock.';
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
        <h2 class="mb-1 fw-bold">Update Stock</h2>
        <div class="avm-muted"><?= htmlspecialchars($item['product_name']) ?> (<?= htmlspecialchars($item['category']) ?>)</div>
    </div>
    <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-md-4">
        <div class="card avm-card h-100">
            <div class="card-body text-center">
                <div class="small avm-muted">Current Stock</div>
                <div class="display-6 avm-metric <?= $currentQty <= (float)($item['min_stock'] ?? 0) ? 'text-danger' : '' ?>">
                    <?= number_format($currentQty, 2) ?>
                </div>
                <div class="text-muted"><?= htmlspecialchars($item['unit']) ?> · Min: <?= number_format((float)($item['min_stock'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-8">
        <div class="card avm-card">
            <div class="card-body">
                <form method="post" action="<?= APP_BASE ?>/inventory/stock_update.php?id=<?= $id ?>">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (['add' => 'Add Stock', 'subtract' => 'Remove Stock', 'set' => 'Set Exact Qty'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="stock_action" id="action_<?= $val ?>"
                                           value="<?= $val ?>" <?= $form['stock_action'] === $val ? 'checked' : '' ?> required>
                                    <label class="form-check-label" for="action_<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($errors['stock_action'])): ?>
                            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['stock_action']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="adjust_qty" class="form-label">Quantity</label>
                        <input type="number" name="adjust_qty" id="adjust_qty" step="0.01" min="0"
                               class="form-control form-control-lg <?= isset($errors['adjust_qty']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($form['adjust_qty']) ?>" required>
                        <?php if (!empty($errors['adjust_qty'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['adjust_qty']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-avm-gold">Apply Update</button>
                    <a href="<?= APP_BASE ?>/inventory/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary">Edit full item</a>
                </form>
            </div>
        </div>
    </div>
</div>

        </main>
    </div>
</div>

<style>@media (min-width:992px){#avmSidebar.offcanvas-lg{position:fixed;top:56px;bottom:0;left:0;transform:none;visibility:visible!important;}}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
