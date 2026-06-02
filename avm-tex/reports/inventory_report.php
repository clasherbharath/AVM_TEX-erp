<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Inventory Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$filterStatus = trim((string)($_GET['status'] ?? 'all'));
$searchCategory = trim((string)($_GET['category'] ?? ''));

$allInventory = [];
$lowStockItems = [];
$outOfStockItems = [];
$totalValue = 0.0;
$dbError = '';

try {
    $stmt = $pdo->query(
        "SELECT id, product_name, category, quantity, unit, purchase_price, selling_price,
                (quantity * purchase_price) AS stock_value
         FROM inventory ORDER BY product_name ASC"
    );
    $allInventory = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query(
        "SELECT id, product_name, category, quantity, unit, purchase_price,
                (quantity * purchase_price) AS stock_value
         FROM inventory WHERE quantity <= 10 AND quantity > 0 ORDER BY quantity ASC"
    );
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query(
        "SELECT id, product_name, category, unit, purchase_price,
                0 AS quantity, (0 * purchase_price) AS stock_value
         FROM inventory WHERE quantity = 0 ORDER BY product_name ASC"
    );
    $outOfStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($allInventory as $item) {
        $totalValue += (float)$item['stock_value'];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load inventory data.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$displayItems = $allInventory;
if ($filterStatus === 'low') {
    $displayItems = $lowStockItems;
} elseif ($filterStatus === 'out') {
    $displayItems = $outOfStockItems;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Inventory Report</h2>
                    <div class="avm-muted">Stock levels and inventory alerts</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Total Items</div>
                            <div class="h4 mb-0 fw-bold"><?= count($allInventory) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Stock Value</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= format_inr($totalValue) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Low Stock Alert</div>
                            <div class="h4 mb-0 fw-bold text-warning"><?= count($lowStockItems) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Out of Stock</div>
                            <div class="h4 mb-0 fw-bold text-danger"><?= count($outOfStockItems) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/inventory_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="filterStatus" class="form-label small fw-semibold">Filter</label>
                            <select id="filterStatus" name="status" class="form-select">
                                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Items</option>
                                <option value="low" <?= $filterStatus === 'low' ? 'selected' : '' ?>>Low Stock (≤ 10)</option>
                                <option value="out" <?= $filterStatus === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <button type="submit" class="btn btn-avm-gold w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Purchase Price</th>
                                <th>Stock Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($displayItems) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No items found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($displayItems as $item): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                                        <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                        <td><?= htmlspecialchars($item['unit']) ?></td>
                                        <td><?= format_inr((float)$item['purchase_price']) ?></td>
                                        <td class="fw-semibold"><?= format_inr((float)($item['stock_value'] ?? $item['quantity'] * $item['purchase_price'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
