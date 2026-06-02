<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory_validation.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Dashboard • A.V.M TEX ERP System';
$activeMenu = 'Dashboard';

$customerCount = 0;
$invoiceCount = 0;
$transactionCount = 0;
$pendingPaymentsCount = 0;
$lowStockCount = 0;
$currentMonthRevenue = 0.0;
$previousMonthRevenue = 0.0;
$monthlySales = [];
$paymentMethodBreakdown = [];
$inventoryStatus = [
    'In Stock' => 0,
    'Low Stock' => 0,
    'Out of Stock' => 0,
];
$dbError = '';

try {
    $customerCount = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $invoiceCount = (int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
    $transactionCount = (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    $pendingPaymentsCount = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'")->fetchColumn();
    $lowStockCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM inventory WHERE quantity <= ' . (int)INVENTORY_LOW_STOCK_THRESHOLD
    )->fetchColumn();

    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('-1 month'));

    $revenueStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COALESCE(SUM(grand_total), 0) AS revenue
         FROM invoices
         WHERE status = 'paid' AND DATE_FORMAT(invoice_date, '%Y-%m') IN (:currentMonth, :previousMonth)
         GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')"
    );
    $revenueStmt->execute([
        ':currentMonth' => $currentMonth,
        ':previousMonth' => $previousMonth,
    ]);
    $revenueRows = $revenueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($revenueRows as $row) {
        if ($row['month'] === $currentMonth) {
            $currentMonthRevenue = (float)$row['revenue'];
        }
        if ($row['month'] === $previousMonth) {
            $previousMonthRevenue = (float)$row['revenue'];
        }
    }

    $salesStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COALESCE(SUM(grand_total), 0) AS revenue
         FROM invoices
         WHERE status = 'paid' AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
         GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
         ORDER BY month ASC"
    );
    $salesStmt->execute();
    $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $lastTwelveMonths = [];
    for ($i = 11; $i >= 0; $i--) {
        $lastTwelveMonths[] = date('Y-m', strtotime("-{$i} months"));
    }

    $salesMap = array_fill_keys($lastTwelveMonths, 0.0);
    foreach ($salesRows as $row) {
        $salesMap[$row['month']] = (float)$row['revenue'];
    }

    foreach ($salesMap as $month => $revenue) {
        $monthlySales[] = [
            'label' => date('M Y', strtotime($month . '-01')),
            'value' => $revenue,
        ];
    }

    $paymentStmt = $pdo->query(
        'SELECT payment_method, COUNT(*) AS total
         FROM transactions
         GROUP BY payment_method
         ORDER BY total DESC'
    );
    $paymentMethodBreakdown = $paymentStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Fixed repeated named placeholder to avoid PDO SQLSTATE[HY093]
    $inventoryStmt = $pdo->prepare(
        'SELECT
            SUM(quantity > :thresholdHigh) AS in_stock,
            SUM(quantity <= :thresholdLow AND quantity > 0) AS low_stock,
            SUM(quantity = 0) AS out_of_stock
         FROM inventory'
    );
    $inventoryStmt->execute([
        ':thresholdHigh' => INVENTORY_LOW_STOCK_THRESHOLD,
        ':thresholdLow' => INVENTORY_LOW_STOCK_THRESHOLD,
    ]);
    $inventoryStatusRow = $inventoryStmt->fetch(PDO::FETCH_ASSOC);
    if ($inventoryStatusRow) {
        $inventoryStatus['In Stock'] = (int)$inventoryStatusRow['in_stock'];
        $inventoryStatus['Low Stock'] = (int)$inventoryStatusRow['low_stock'];
        $inventoryStatus['Out of Stock'] = (int)$inventoryStatusRow['out_of_stock'];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Unable to load dashboard metrics.';
}

$revenueChange = $previousMonthRevenue > 0
    ? round((($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100, 1)
    : null;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>

        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Dashboard</h2>
                    <div class="avm-muted">Welcome back, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>. Live ERP insights are below.</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">Reports</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Revenue (Month)</div>
                            <div class="h3 avm-metric mb-1"><?= format_inr($currentMonthRevenue) ?></div>
                            <?php if ($revenueChange !== null): ?>
                                <div class="small <?= $revenueChange >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $revenueChange >= 0 ? '+' : '' ?><?= $revenueChange ?>% vs last month
                                </div>
                            <?php else: ?>
                                <div class="small avm-muted">No prior month comparison available.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Customers</div>
                            <div class="h3 avm-metric mb-1"><?= $customerCount ?></div>
                            <div class="small avm-muted"><a href="<?= APP_BASE ?>/customers/index.php" class="text-decoration-none">View customers</a></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Invoices</div>
                            <div class="h3 avm-metric mb-1"><?= $invoiceCount ?></div>
                            <div class="small <?= $pendingPaymentsCount > 0 ? 'text-warning' : 'avm-muted' ?>">
                                <?= $pendingPaymentsCount ?> pending payments
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Transactions</div>
                            <div class="h3 avm-metric mb-1"><?= $transactionCount ?></div>
                            <div class="small avm-muted"><a href="<?= APP_BASE ?>/transactions/index.php" class="text-decoration-none">View transactions</a></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Pending Payments</div>
                            <div class="h3 avm-metric mb-1"><?= $pendingPaymentsCount ?></div>
                            <div class="small avm-muted">Invoices awaiting settlement</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Low Stock Items</div>
                            <div class="h3 avm-metric mb-1 <?= $lowStockCount > 0 ? 'text-danger' : '' ?>"><?= $lowStockCount ?></div>
                            <div class="small avm-muted">Threshold: <?= INVENTORY_LOW_STOCK_THRESHOLD ?> units</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-8">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-1 fw-semibold">Monthly Sales</h3>
                                    <p class="small avm-muted mb-0">Last 12 months revenue (paid invoices).</p>
                                </div>
                            </div>
                            <div class="chart-shell position-relative" style="min-height: 320px;">
                                <canvas id="salesLineChart"></canvas>
                                <div id="salesLoadingState" class="chart-overlay">Loading sales chart...</div>
                                <div id="salesEmptyState" class="chart-empty d-none">No sales data available yet.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-1 fw-semibold">Payment Methods</h3>
                                    <p class="small avm-muted mb-0">Transaction distribution by payment type.</p>
                                </div>
                            </div>
                            <div class="chart-shell position-relative" style="min-height: 320px;">
                                <canvas id="paymentMethodChart"></canvas>
                                <div id="paymentLoadingState" class="chart-overlay">Loading payment chart...</div>
                                <div id="paymentEmptyState" class="chart-empty d-none">No transaction payment data yet.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-1 fw-semibold">Inventory Status</h3>
                                    <p class="small avm-muted mb-0">Stock health across inventory categories.</p>
                                </div>
                            </div>
                            <div class="chart-shell position-relative" style="min-height: 360px;">
                                <canvas id="inventoryStatusChart"></canvas>
                                <div id="inventoryLoadingState" class="chart-overlay">Loading inventory chart...</div>
                                <div id="inventoryEmptyState" class="chart-empty d-none">No inventory stock data available.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
@media (min-width: 992px) {
    #avmSidebar.offcanvas-lg {
        position: fixed;
        top: 56px;
        bottom: 0;
        left: 0;
        transform: none;
        visibility: visible !important;
    }
}
.chart-shell {
    position: relative;
}
.chart-overlay,
.chart-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.85);
    color: #212529;
    font-weight: 600;
    border-radius: 0.5rem;
    text-align: center;
}
.chart-empty {
    background: rgba(248, 249, 250, 0.92);
}
.chart-overlay { z-index: 2; }
.chart-empty { z-index: 3; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.2/dist/chart.umd.min.js"></script>
<script>
    const chartData = {
        salesChartData: <?= json_encode(array_column($monthlySales, 'label')) ?>,
        salesChartValues: <?= json_encode(array_column($monthlySales, 'value'), JSON_NUMERIC_CHECK) ?>,
        paymentLabels: <?= json_encode(array_keys($paymentMethodBreakdown)) ?>,
        paymentValues: <?= json_encode(array_values($paymentMethodBreakdown), JSON_NUMERIC_CHECK) ?>,
        inventoryLabels: <?= json_encode(array_keys($inventoryStatus)) ?>,
        inventoryValues: <?= json_encode(array_values($inventoryStatus), JSON_NUMERIC_CHECK) ?>,
    };

    const chartColors = ['#c9a227', '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b'];

    function hideElement(selector) {
        document.querySelector(selector)?.classList.add('d-none');
    }

    function showElement(selector) {
        document.querySelector(selector)?.classList.remove('d-none');
    }

    function setEmptyState(selector, message) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = message;
            showElement(selector);
        }
    }

    function loadLocalChartJs(onSuccess, onFailure) {
        if (typeof Chart !== 'undefined') {
            if (typeof onSuccess === 'function') {
                onSuccess();
            }
            return;
        }

        if (window.avmChartJsLoading) {
            return;
        }

        window.avmChartJsLoading = true;

        const script = document.createElement('script');
        script.src = '<?= APP_BASE ?>/assets/js/chart.umd.min.js';
        script.onload = () => {
            window.avmChartJsLoading = false;
            window.avmChartJsLoaded = true;
            console.log('Loaded local Chart.js fallback.');
            if (typeof onSuccess === 'function') {
                onSuccess();
            }
        };
        script.onerror = () => {
            window.avmChartJsLoading = false;
            window.avmChartJsLoaded = false;
            console.error('Local Chart.js fallback failed to load.');
            if (typeof onFailure === 'function') {
                onFailure();
            }
        };
        document.head.appendChild(script);
    }

    function createLineChart(elementId, labels, dataValues) {
        return new Chart(document.getElementById(elementId), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: dataValues,
                    borderColor: '#c9a227',
                    backgroundColor: 'rgba(201, 162, 39, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#c9a227',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: {
                            callback: value => '₹ ' + value.toLocaleString(),
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: context => 'Revenue: ₹ ' + Number(context.parsed.y).toLocaleString(),
                        },
                    },
                },
            },
        });
    }

    function createPieChart(elementId, labels, values) {
        return new Chart(document.getElementById(elementId), {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: chartColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: context => context.label + ': ' + context.parsed + ' transaction(s)',
                        },
                    },
                },
            },
        });
    }

    function createBarChart(elementId, labels, values) {
        return new Chart(document.getElementById(elementId), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Inventory Count',
                    data: values,
                    backgroundColor: ['#2ca02c', '#ffbb33', '#d62728'],
                    borderColor: ['#2ca02c', '#ffbb33', '#d62728'],
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: context => context.dataset.label + ': ' + context.parsed.y,
                        },
                    },
                },
            },
        });
    }

    function showChartLibraryFailure() {
        const failureMessage = 'Chart library failed to load.';
        setEmptyState('#salesEmptyState', failureMessage);
        setEmptyState('#paymentEmptyState', failureMessage);
        setEmptyState('#inventoryEmptyState', failureMessage);

        hideElement('#salesLineChart');
        hideElement('#paymentMethodChart');
        hideElement('#inventoryStatusChart');

        hideElement('#salesLoadingState');
        hideElement('#paymentLoadingState');
        hideElement('#inventoryLoadingState');
    }

    function initializeCharts() {
        console.log('Dashboard chart data:', chartData);
        console.log('Chart library available:', typeof Chart !== 'undefined');

        if (typeof Chart === 'undefined') {
            console.warn('Chart.js library not loaded from CDN, loading local fallback.');
            loadLocalChartJs(initializeCharts, showChartLibraryFailure);
            return;
        }

        const salesHasData = chartData.salesChartValues.some(value => value > 0);
        const paymentHasData = chartData.paymentValues.length > 0 && chartData.paymentValues.some(value => value > 0);
        const inventoryHasData = chartData.inventoryValues.length > 0 && chartData.inventoryValues.some(value => value > 0);

        const salesDataset = salesHasData ? chartData.salesChartValues : new Array(chartData.salesChartData.length).fill(0);
        const paymentDataset = paymentHasData ? chartData.paymentValues : [0, 0, 0, 0, 0];
        const paymentLabelsFallback = paymentHasData ? chartData.paymentLabels : ['Cash', 'Cheque', 'Bank Transfer', 'Card', 'Other'];
        const inventoryDataset = inventoryHasData ? chartData.inventoryValues : [0, 0, 0];

        try {
            createLineChart('salesLineChart', chartData.salesChartData, salesDataset);
            createPieChart('paymentMethodChart', paymentLabelsFallback, paymentDataset);
            createBarChart('inventoryStatusChart', chartData.inventoryLabels, inventoryDataset);
        } catch (error) {
            console.error('Chart rendering failed:', error);
            showChartLibraryFailure();
            return;
        }

        if (!salesHasData) {
            setEmptyState('#salesEmptyState', 'No sales data available.');
        } else {
            hideElement('#salesEmptyState');
        }

        if (!paymentHasData) {
            setEmptyState('#paymentEmptyState', 'No payment method data available.');
        } else {
            hideElement('#paymentEmptyState');
        }

        if (!inventoryHasData) {
            setEmptyState('#inventoryEmptyState', 'No inventory stock data available.');
        } else {
            hideElement('#inventoryEmptyState');
        }

        hideElement('#salesLoadingState');
        hideElement('#paymentLoadingState');
        hideElement('#inventoryLoadingState');
    }

    document.addEventListener('DOMContentLoaded', initializeCharts);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

