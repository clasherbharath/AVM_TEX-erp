<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Dead Stock Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$period = trim((string)($_GET['period'] ?? 'last_90'));
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$whereClauses = ['1 = 1'];
$params = [];
$rangeLabel = 'Last 90 Days';

if ($period === 'today') {
    $rangeLabel = 'Today';
    $whereClauses[] = 'inv.invoice_date BETWEEN :start_date AND :end_date';
    $params[':start_date'] = date('Y-m-d') . ' 00:00:00';
    $params[':end_date'] = date('Y-m-d') . ' 23:59:59';
} elseif ($period === 'month') {
    $rangeLabel = 'This Month';
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    $whereClauses[] = 'inv.invoice_date BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $start . ' 00:00:00';
    $params[':end_date'] = $end . ' 23:59:59';
} elseif ($period === 'custom' && $startDate !== '' && $endDate !== '') {
    $rangeLabel = 'Custom Range';
    $whereClauses[] = 'inv.invoice_date BETWEEN :start_date AND :end_date';
    $params[':start_date'] = $startDate . ' 00:00:00';
    $params[':end_date'] = $endDate . ' 23:59:59';
} else {
    $rangeLabel = 'Last 90 Days';
    $whereClauses[] = 'inv.invoice_date >= :cutoff_date';
    $params[':cutoff_date'] = date('Y-m-d', strtotime('-90 days')) . ' 00:00:00';
}

$dateCondition = implode(' AND ', $whereClauses);

$deadStockItems = [];
$totalProducts = 0;
$deadStockCount = 0;
$totalInventoryValue = 0.0;
$topDeadStock = [];
$dbError = '';

try {
    $statsStmt = $pdo->query(
        "SELECT
             COUNT(*) AS total_products,
             COALESCE(SUM(quantity * purchase_price), 0) AS total_inventory_value
           FROM inventory"
    );
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalProducts = (int)($stats['total_products'] ?? 0);
    $totalInventoryValue = (float)($stats['total_inventory_value'] ?? 0);

    $query = "SELECT i.id, i.product_name, i.category, i.quantity, i.unit,
                     i.purchase_price, (i.quantity * i.purchase_price) AS inventory_value,
                     COALESCE(MAX(inv.invoice_date), NULL) AS last_sale_date,
                     DATEDIFF(CURRENT_DATE(), MAX(inv.invoice_date)) AS days_since_last_sale
              FROM inventory i
              LEFT JOIN invoice_items ii ON ii.product_id = i.id
              LEFT JOIN invoices inv ON inv.id = ii.invoice_id AND inv.status = 'paid'";

    if ($period === 'custom' || $period === 'today' || $period === 'month') {
        $query .= " AND {$dateCondition}";
    }

    $query .= "
              GROUP BY i.id
              HAVING MAX(inv.invoice_date) IS NULL OR MAX(inv.invoice_date) < DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)
              ORDER BY days_since_last_sale DESC, i.product_name ASC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $deadStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $deadStockCount = count($deadStockItems);

    $topDeadStock = array_slice($deadStockItems, 0, 10);
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load dead stock data.';
}

if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dead_stock_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Product', 'Category', 'Current Stock', 'Inventory Value', 'Last Sale Date', 'Days Since Last Sale']);
    foreach ($deadStockItems as $item) {
        fputcsv($out, [
            $item['product_name'],
            $item['category'],
            $item['quantity'],
            $item['inventory_value'],
            $item['last_sale_date'] ?? 'Never',
            $item['days_since_last_sale'],
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
                    <h2 class="mb-1 fw-bold">Dead Stock Report</h2>
                    <div class="avm-muted">Products with no sales activity in the last 90 days.</div>
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
                            <div class="small text-muted">Dead Stock</div>
                            <div class="h3 mb-0 fw-bold text-danger"><?= $deadStockCount ?></div>
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
                <div class="col-md-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Dead Stock Items</div>
                            <div class="h3 mb-0 fw-bold text-danger"><?= $deadStockCount ?></div>
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
                                    <h5 class="mb-1">Stock Value by Dead Product</h5>
                                    <div class="small text-muted">Highest value inactive inventory.</div>
                                </div>
                            </div>
                            <div class="chart-container" style="min-height: 320px; position: relative;">
                                <canvas id="deadStockValueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-1">Sales Inactivity Trend</h5>
                                    <div class="small text-muted">Days since last sale for top dead stock.</div>
                                </div>
                            </div>
                            <div class="chart-container" style="min-height: 320px; position: relative;">
                                <canvas id="deadStockTrendChart"></canvas>
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
                                <th>Current Stock</th>
                                <th>Inventory Value</th>
                                <th>Last Sale Date</th>
                                <th>Days Since Last Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($deadStockItems) === 0): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No dead stock found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($deadStockItems as $item): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                                        <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                        <td class="fw-semibold text-success"><?= format_inr((float)$item['inventory_value']) ?></td>
                                        <td><?= htmlspecialchars($item['last_sale_date'] ?? 'Never') ?></td>
                                        <td><?= htmlspecialchars((string)(int)$item['days_since_last_sale']) ?></td>
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
    const deadStockLabels = <?= json_encode(array_column($topDeadStock, 'product_name'), JSON_THROW_ON_ERROR) ?>;
    const deadStockValues = <?= json_encode(array_map(static fn(array $item) => (float)$item['inventory_value'], $topDeadStock), JSON_NUMERIC_CHECK) ?>;
    const deadStockDays = <?= json_encode(array_map(static fn(array $item) => (int)$item['days_since_last_sale'], $topDeadStock), JSON_NUMERIC_CHECK) ?>;

    const ctxValue = document.getElementById('deadStockValueChart');
    if (ctxValue) {
        new Chart(ctxValue, {
            type: 'bar',
            data: {
                labels: deadStockLabels,
                datasets: [{
                    label: 'Inventory Value',
                    data: deadStockValues,
                    backgroundColor: '#4e73df',
                }],
            },
            options: {
                scales: {
                    y: { ticks: { callback: value => '₹ ' + value.toLocaleString() } },
                },
            },
        });
    }

    const ctxTrend = document.getElementById('deadStockTrendChart');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: deadStockLabels,
                datasets: [{
                    label: 'Days Since Last Sale',
                    data: deadStockDays,
                    borderColor: '#f6c23e',
                    backgroundColor: 'rgba(246,194,62,0.25)',
                    fill: true,
                }],
            },
            options: {
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
