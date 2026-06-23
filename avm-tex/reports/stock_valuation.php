<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Stock Valuation • A.V.M TEX ERP';
$activeMenu = 'Reports';

$period = trim((string)($_GET['period'] ?? 'all'));
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$whereClause = '1 = 1';
$params = [];
$rangeLabel = 'All Dates';

if ($period === 'today') {
    $rangeLabel = 'Today';
    $whereClause = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = date('Y-m-d') . ' 00:00:00';
    $params[':end_date'] = date('Y-m-d') . ' 23:59:59';
} elseif ($period === 'month') {
    $rangeLabel = 'This Month';
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    $whereClause = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $start . ' 00:00:00';
    $params[':end_date'] = $end . ' 23:59:59';
} elseif ($period === 'last_90') {
    $rangeLabel = 'Last 90 Days';
    $start = date('Y-m-d', strtotime('-90 days'));
    $end = date('Y-m-d');
    $whereClause = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $start . ' 00:00:00';
    $params[':end_date'] = $end . ' 23:59:59';
} elseif ($period === 'custom' && $startDate !== '' && $endDate !== '') {
    $rangeLabel = 'Custom Range';
    $whereClause = 'updated_at BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $startDate . ' 00:00:00';
    $params[':end_date'] = $endDate . ' 23:59:59';
}

$inventory = [];
$stats = [
    'total_value' => 0.0,
    'total_products' => 0,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
];
$topValueProducts = [];
$categoryValue = [];
$dbError = '';

try {
    $statsStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(quantity * purchase_price), 0) AS total_value,
            SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END) AS low_stock_count,
            SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock_count
         FROM inventory
         WHERE {$whereClause}"
    );
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $query = "SELECT id, product_name, category, quantity, unit,
                     purchase_price, min_stock,
                     (quantity * purchase_price) AS inventory_value
              FROM inventory
              WHERE {$whereClause}
              ORDER BY inventory_value DESC, product_name ASC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $topValueProducts = array_slice($inventory, 0, 10);

    foreach ($inventory as $item) {
        $category = trim((string)($item['category'] ?? 'Uncategorized'));
        $categoryValue[$category] = ($categoryValue[$category] ?? 0) + (float)$item['inventory_value'];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load stock valuation data.';
}

if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock_valuation_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Product', 'Category', 'Quantity', 'Min Stock', 'Purchase Price', 'Inventory Value', 'Unit']);
    foreach ($inventory as $item) {
        fputcsv($out, [
            $item['product_name'],
            $item['category'],
            $item['quantity'],
            $item['min_stock'],
            $item['purchase_price'],
            $item['inventory_value'],
            $item['unit'],
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
                    <h2 class="mb-1 fw-bold">Stock Valuation</h2>
                    <div class="avm-muted">Inventory value ranked by product and category.</div>
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
                            <div class="small text-muted">Total Inventory Value</div>
                            <div class="h3 mb-0 fw-bold text-success"><?= format_inr((float)$stats['total_value']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Total Products</div>
                            <div class="h3 mb-0 fw-bold"><?= (int)$stats['total_products'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Low Stock Count</div>
                            <div class="h3 mb-0 fw-bold text-warning"><?= (int)$stats['low_stock_count'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Out of Stock Count</div>
                            <div class="h3 mb-0 fw-bold text-danger"><?= (int)$stats['out_of_stock_count'] ?></div>
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
                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-1">Stock Value by Product</h5>
                                    <div class="small text-muted">Top 10 products by inventory value.</div>
                                </div>
                            </div>
                            <div class="chart-container" style="min-height: 320px; position: relative;">
                                <canvas id="valueByProductChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-1">Category Valuation</h5>
                                    <div class="small text-muted">Inventory value per category.</div>
                                </div>
                            </div>
                            <div class="chart-container" style="min-height: 320px; position: relative;">
                                <canvas id="categoryValueChart"></canvas>
                            </div>
                        </div>
                    </div>
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
                                <th>Min Stock</th>
                                <th>Purchase Price</th>
                                <th>Inventory Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($inventory) === 0): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No products found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($inventory as $item): ?>
                                    <tr class="<?= (float)$item['quantity'] === 0 ? 'table-danger' : ((float)$item['quantity'] <= (float)$item['min_stock'] ? 'table-warning' : '') ?>">
                                        <td class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                                        <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                        <td><?= number_format((float)$item['min_stock'], 2) ?></td>
                                        <td><?= format_inr((float)$item['purchase_price']) ?></td>
                                        <td class="fw-semibold text-success"><?= format_inr((float)$item['inventory_value']) ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.2/dist/chart.umd.min.js"></script>
<script>
    const productLabels = <?= json_encode(array_column($topValueProducts, 'product_name'), JSON_THROW_ON_ERROR) ?>;
    const productValues = <?= json_encode(array_map(static fn(array $item) => (float)$item['inventory_value'], $topValueProducts), JSON_NUMERIC_CHECK) ?>;
    const categoryLabels = <?= json_encode(array_keys($categoryValue), JSON_THROW_ON_ERROR) ?>;
    const categoryValues = <?= json_encode(array_values($categoryValue), JSON_NUMERIC_CHECK) ?>;

    const ctxProduct = document.getElementById('valueByProductChart');
    if (ctxProduct) {
        new Chart(ctxProduct, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Inventory Value',
                    data: productValues,
                    backgroundColor: '#1cc88a',
                }],
            },
            options: {
                scales: {
                    y: { ticks: { callback: value => '₹ ' + value.toLocaleString() } },
                },
            },
        });
    }

    const ctxCategory = document.getElementById('categoryValueChart');
    if (ctxCategory) {
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: ['#4e73df', '#f6c23e', '#e74a3b', '#36b9cc', '#858796'],
                }],
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
