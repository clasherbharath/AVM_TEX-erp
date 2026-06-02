<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Transaction Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$filterType = trim((string)($_GET['type'] ?? ''));
$month = trim((string)($_GET['month'] ?? date('Y-m')));

$methodStats = [];
$typeStats = [];
$monthlyData = [];
$totalAmount = 0.0;
$dbError = '';

$validMethods = [
    'cash' => 'Cash',
    'cheque' => 'Cheque',
    'bank_transfer' => 'Bank Transfer',
    'card' => 'Card',
    'other' => 'Other',
];

$validTypes = [
    'payment' => 'Payment',
    'refund' => 'Refund',
    'adjustment' => 'Adjustment',
    'credit_memo' => 'Credit Memo',
];

try {
    $stmt = $pdo->query(
        "SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
         FROM transactions GROUP BY payment_method ORDER BY total DESC"
    );
    $methodStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query(
        "SELECT transaction_type, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
         FROM transactions GROUP BY transaction_type ORDER BY total DESC"
    );
    $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(transaction_date, '%Y-%m-%d') AS date, COUNT(*) AS count,
                COALESCE(SUM(amount), 0) AS total
         FROM transactions WHERE DATE_FORMAT(transaction_date, '%Y-%m') = :month
         GROUP BY DATE_FORMAT(transaction_date, '%Y-%m-%d') ORDER BY date DESC"
    );
    $stmt->execute([':month' => $month]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $totalStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions");
    $totalAmount = (float)($totalStmt->fetchColumn() ?? 0);
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load transaction data.';
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
                    <h2 class="mb-1 fw-bold">Transaction Report</h2>
                    <div class="avm-muted">Payment methods and transaction analysis</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/transaction_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="monthInput" class="form-label small fw-semibold">Month</label>
                            <input type="month" id="monthInput" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <button type="submit" class="btn btn-avm-gold w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="small text-muted">Total Transaction Amount</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= format_inr($totalAmount) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0">By Payment Method</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Method</th>
                                        <th>Count</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($methodStats as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($validMethods[$stat['payment_method']] ?? $stat['payment_method']) ?></td>
                                            <td><?= (int)$stat['count'] ?></td>
                                            <td class="fw-semibold"><?= format_inr((float)$stat['total']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0">By Transaction Type</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Count</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($typeStats as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($validTypes[$stat['transaction_type']] ?? $stat['transaction_type']) ?></td>
                                            <td><?= (int)$stat['count'] ?></td>
                                            <td class="fw-semibold"><?= format_inr((float)$stat['total']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0">Daily Transactions for <?= htmlspecialchars($month) ?></h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($monthlyData) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monthlyData as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['date']) ?></td>
                                        <td><?= (int)$row['count'] ?></td>
                                        <td class="fw-semibold text-success"><?= format_inr((float)$row['total']) ?></td>
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
