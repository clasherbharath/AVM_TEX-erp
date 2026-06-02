<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Sales Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$reportType = trim((string)($_GET['type'] ?? 'monthly'));
$month = trim((string)($_GET['month'] ?? date('Y-m')));
$year = trim((string)($_GET['year'] ?? date('Y')));

$salesData = [];
$totalRevenue = 0.0;
$dbError = '';

try {
    if ($reportType === 'daily') {
        $stmt = $pdo->prepare(
            "SELECT DATE(invoice_date) AS date, COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
             FROM invoices WHERE status = 'paid' AND YEAR(invoice_date) = :year
             GROUP BY DATE(invoice_date) ORDER BY date DESC"
        );
        $stmt->execute([':year' => (int)$year]);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($reportType === 'monthly') {
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
             FROM invoices WHERE status = 'paid' AND YEAR(invoice_date) = :year
             GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month DESC"
        );
        $stmt->execute([':year' => (int)$year]);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $stmt = $pdo->prepare(
            "SELECT YEAR(invoice_date) AS year, COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
             FROM invoices WHERE status = 'paid'
             GROUP BY YEAR(invoice_date) ORDER BY year DESC"
        );
        $stmt->execute([]);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    foreach ($salesData as $row) {
        $totalRevenue += (float)$row['revenue'];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load sales data.';
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
                    <h2 class="mb-1 fw-bold">Sales Report</h2>
                    <div class="avm-muted">Revenue and invoice analysis</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/sales_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="reportType" class="form-label small fw-semibold">Report Type</label>
                            <select id="reportType" name="type" class="form-select">
                                <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="yearly" <?= $reportType === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                            </select>
                        </div>
                        <?php if ($reportType !== 'yearly'): ?>
                            <div class="col-12 col-md-4">
                                <label for="yearInput" class="form-label small fw-semibold">Year</label>
                                <input type="number" id="yearInput" name="year" class="form-control" 
                                       value="<?= htmlspecialchars($year) ?>" min="2020" max="<?= date('Y') ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-12 col-md-4">
                            <button type="submit" class="btn btn-avm-gold w-100">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Total Revenue</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= format_inr($totalRevenue) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Total Invoices</div>
                            <div class="h4 mb-0 fw-bold"><?= count($salesData) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Average Per Period</div>
                            <div class="h4 mb-0 fw-bold"><?= count($salesData) > 0 ? format_inr($totalRevenue / count($salesData)) : '₹ 0.00' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= ucfirst($reportType) ?></th>
                                <th>Invoices</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($salesData) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No sales data found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salesData as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['date'] ?? $row['month'] ?? $row['year']) ?></td>
                                        <td><?= (int)$row['invoice_count'] ?></td>
                                        <td class="fw-semibold text-success"><?= format_inr((float)$row['revenue']) ?></td>
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
