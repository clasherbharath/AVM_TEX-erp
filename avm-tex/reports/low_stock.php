<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Low Stock Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$period = trim((string)($_GET['period'] ?? 'all'));
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$dateCondition = '';
$params = [];
$rangeLabel = 'All Dates';

if ($period === 'today') {
    $rangeLabel = 'Today';
    $dateCondition = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = date('Y-m-d') . ' 00:00:00';
    $params[':end_date'] = date('Y-m-d') . ' 23:59:59';
} elseif ($period === 'month') {
    $rangeLabel = 'This Month';
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    $dateCondition = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $start . ' 00:00:00';
    $params[':end_date'] = $end . ' 23:59:59';
} elseif ($period === 'last_90') {
    $rangeLabel = 'Last 90 Days';
    $start = date('Y-m-d', strtotime('-90 days'));
    $end = date('Y-m-d');
    $dateCondition = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $start . ' 00:00:00';
    $params[':end_date'] = $end . ' 23:59:59';
} elseif ($period === 'custom' && $startDate !== '' && $endDate !== '') {
    $rangeLabel = 'Custom Range';
    $dateCondition = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $startDate . ' 00:00:00';
    $params[':end_date'] = $endDate . ' 23:59:59';
}

$whereClause = 'quantity <= min_stock';
if ($dateCondition !== '') {
    $whereClause .= ' AND ' . $dateCondition;
}

$lowStockItems = [];
$totalProducts = 0;
$outOfStock = 0;
$totalLowStockValue = 0.0;
$categoryDistribution = [];
$dbError = '';

try {
    $statsStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(quantity * purchase_price), 0) AS total_inventory_value
         FROM inventory"
    );
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalProducts = (int)($stats['total_products'] ?? 0);
    $totalInventoryValue = (float)($stats['total_inventory_value'] ?? 0);

    $query = "SELECT id, product_name, category, quantity, min_stock, unit,
                     purchase_price, updated_at,
                     GREATEST(min_stock - quantity, 0) AS shortage
              FROM inventory
              WHERE {$whereClause}
              ORDER BY quantity ASC, product_name ASC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $outOfStock = 0;
    foreach ($lowStockItems as $item) {
        $category = trim((string)($item['category'] ?? 'Uncategorized'));
        $categoryDistribution[$category] = ($categoryDistribution[$category] ?? 0) + 1;
        if ((float)$item['quantity'] === 0.0) {
            $outOfStock++;
        }
        $totalLowStockValue += (float)$item['quantity'] * (float)$item['purchase_price'];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load low stock data.';
}

if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="low_stock_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Product', 'Category', 'Quantity', 'Min Stock', 'Shortage', 'Unit', 'Updated At']);
    foreach ($lowStockItems as $item) {
        fputcsv($out, [
            $item['product_name'],
            $item['category'],
            $item['quantity'],
            $item['min_stock'],
            $item['shortage'],
            $item['unit'],
            $item['updated_at'],
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Low Stock Report</h2>
                    <div class="avm-muted">Items at or below their minimum inventory threshold.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-secondary btn-sm">Export CSV</a>
                </div>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Low Stock Items</div>
                            <div class="h3 mb-0 fw-bold text-warning"><?= count($lowStockItems) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Out of Stock</div>
                            <div class="h3 mb-0 fw-bold text-danger"><?= $outOfStock ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Total Products</div>
                            <div class="h3 mb-0 fw-bold"><?= $totalProducts ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Inventory Value</div>
                            <div class="h3 mb-0 fw-bold text-success"><?= format_inr($totalInventoryValue) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="period">Date Filter</label>
                            <select id="period" name="period" class="form-select">
                                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Dates</option>
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                                <option value="last_90" <?= $period === 'last_90' ? 'selected' : '' ?>>Last 90 Days</option>
                                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button type="submit" class="btn btn-avm-gold">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-5">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-1">Low Stock Distribution</h5>
                                    <div class="small text-muted">By category within <?= htmlspecialchars($rangeLabel) ?>.</div>
                                </div>
                            </div>
                            <div class="chart-container" style="min-height: 320px; position: relative;">
                                <canvas id="lowStockDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card avm-card">
                        <div class="card-body">
                            <h5 class="mb-3">Item Snapshot</h5>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Min Stock</th>
                                            <th>Shortage</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($lowStockItems) === 0): ?>
                                            <tr><td colspan="6" class="text-center py-4 text-muted">No low stock items found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($lowStockItems as $item): ?>
                                                <?php
                                                    $quantity = (float)$item['quantity'];
                                                    $minStock = (float)$item['min_stock'];
                                                    $statusClass = $quantity === 0 ? 'table-danger' : 'table-warning';
                                                    $statusLabel = $quantity === 0 ? 'Out of Stock' : 'Low Stock';
                                                ?>
                                                <tr class="<?= $statusClass ?>">
                                                    <td class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></td>
                                                    <td><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                                                    <td><?= number_format($quantity, 2) ?></td>
                                                    <td><?= number_format($minStock, 2) ?></td>
                                                    <td><?= number_format((float)$item['shortage'], 2) ?></td>
                                                    <td><?= $statusLabel ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.2/dist/chart.umd.min.js"></script>
<script>
    const lowStockCategoryLabels = <?= json_encode(array_keys($categoryDistribution), JSON_THROW_ON_ERROR) ?>;
    const lowStockCategoryValues = <?= json_encode(array_values($categoryDistribution), JSON_NUMERIC_CHECK) ?>;

    const ctxLowStock = document.getElementById('lowStockDistributionChart');
    if (ctxLowStock) {
        new Chart(ctxLowStock, {
            type: 'doughnut',
            data: {
                labels: lowStockCategoryLabels,
                datasets: [{
                    data: lowStockCategoryValues,
                    backgroundColor: ['#f6c23e', '#e74a3b', '#1cc88a', '#36b9cc', '#858796'],
                    borderWidth: 1,
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
