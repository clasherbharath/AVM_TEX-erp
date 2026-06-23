<?php
declare(strict_types=1);

/**
 * Accounts Receivable Reconciliation Report
 *
 * Shows per-invoice settlement data including total payments, refunds and outstanding balance.
 * Supports preset filters: `today`, `month`, and a `custom` range via `start_date`/`end_date` GET params.
 * CSV export available via `?export=csv`.
 *
 * SQL notes:
 * - Aggregates transactions per invoice using a single LEFT JOIN + GROUP BY to minimize round-trips.
 * - Uses CASE expressions to categorize transaction types in the aggregation.
 */

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'AR Reconciliation • A.V.M TEX ERP';
$activeMenu = 'Reports';

// Parse date filter
$filter = $_GET['filter'] ?? 'month';
$startDate = '';
$endDate = '';
if ($filter === 'today') {
    $startDate = date('Y-m-d');
    $endDate = $startDate;
} elseif ($filter === 'custom' && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
} else {
    // Default: this month
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

// Ensure valid date format (basic)
try {
    $sd = new DateTime($startDate);
    $ed = new DateTime($endDate);
} catch (Exception $e) {
    $sd = new DateTime(date('Y-m-01'));
    $ed = new DateTime(date('Y-m-t'));
}

$start = $sd->format('Y-m-d');
$end = $ed->format('Y-m-d');

// Build main query: aggregate transactions per invoice.
$sql = "
    SELECT
        i.id AS invoice_id,
        i.invoice_number,
        COALESCE(c.customer_name, '') AS customer_name,
        i.grand_total,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) AS total_payments,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'refund' THEN t.amount ELSE 0 END), 0) AS total_refunds,
        COALESCE(SUM(
            CASE WHEN t.transaction_type = 'payment' THEN t.amount
                 WHEN t.transaction_type IN ('refund','credit_memo') THEN -t.amount
                 ELSE 0 END
        ), 0) AS net_applied
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN transactions t ON t.invoice_id = i.id
    WHERE DATE(i.invoice_date) BETWEEN :start AND :end
    GROUP BY i.id
    ORDER BY i.invoice_date DESC, i.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':start' => $start, ':end' => $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ar_reconciliation_' . $start . '_' . $end . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Invoice Number', 'Customer Name', 'Invoice Total', 'Total Payments', 'Total Refunds', 'Outstanding Balance']);
    foreach ($rows as $r) {
        $grand = (float)$r['grand_total'];
        $net = (float)$r['net_applied'];
        $balance = max(0, round($grand - $net, 2));
        fputcsv($out, [$r['invoice_number'], $r['customer_name'], number_format($grand, 2), number_format((float)$r['total_payments'], 2), number_format((float)$r['total_refunds'], 2), number_format($balance, 2)]);
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
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 fw-bold">Accounts Receivable Reconciliation</h2>
                    <div class="avm-muted">Per-invoice settlement and outstanding balances.</div>
                </div>
                <div>
                    <a href="?export=csv&filter=<?= htmlspecialchars($filter) ?>&start_date=<?= htmlspecialchars($start) ?>&end_date=<?= htmlspecialchars($end) ?>" class="btn btn-outline-secondary btn-sm">Export CSV</a>
                </div>
            </div>

            <form class="row g-2 mb-3" method="get" action="">
                <div class="col-auto">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="custom" <?= $filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>" class="form-control">
                </div>
                <div class="col-auto">
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>" class="form-control">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Apply</button>
                </div>
            </form>

            <div class="card avm-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th class="text-end">Invoice Total</th>
                                    <th class="text-end">Payments</th>
                                    <th class="text-end">Refunds</th>
                                    <th class="text-end">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="6" class="text-center py-4 avm-muted">No invoices found for the selected range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r):
                                        $grand = (float)$r['grand_total'];
                                        $payments = (float)$r['total_payments'];
                                        $refunds = (float)$r['total_refunds'];
                                        $net = (float)$r['net_applied'];
                                        $balance = max(0, round($grand - $net, 2));
                                        // Color rules: green = reconciled, yellow = partial, red = outstanding
                                        if ($balance <= 0.01) {
                                            $rowClass = 'table-success';
                                        } elseif ($net > 0) {
                                            $rowClass = 'table-warning';
                                        } else {
                                            $rowClass = 'table-danger';
                                        }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><a href="<?= APP_BASE ?>/billing/invoice_view.php?id=<?= (int)$r['invoice_id'] ?>"><?= htmlspecialchars($r['invoice_number']) ?></a></td>
                                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                        <td class="text-end"><?= format_inr($grand) ?></td>
                                        <td class="text-end"><?= format_inr($payments) ?></td>
                                        <td class="text-end"><?= format_inr($refunds) ?></td>
                                        <td class="text-end fw-semibold"><?= format_inr($balance) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
