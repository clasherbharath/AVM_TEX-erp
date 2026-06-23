<?php
declare(strict_types=1);

/**
 * Accounts Payable Reconciliation Report
 *
 * Shows per-purchase-order settlement including supplier payments and outstanding balance.
 * Supports preset filters: `today`, `month`, and `custom` range via `start_date`/`end_date` GET params.
 * CSV export via `?export=csv`.
 *
 * SQL notes:
 * - Uses a derived table to sum supplier payments per purchase order and joins once.
 */

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'AP Reconciliation • A.V.M TEX ERP';
$activeMenu = 'Reports';

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
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

try {
    $sd = new DateTime($startDate);
    $ed = new DateTime($endDate);
} catch (Exception $e) {
    $sd = new DateTime(date('Y-m-01'));
    $ed = new DateTime(date('Y-m-t'));
}

$start = $sd->format('Y-m-d');
$end = $ed->format('Y-m-d');

$sql = "
    SELECT
        po.id AS purchase_order_id,
        po.po_number,
        COALESCE(s.supplier_name, '') AS supplier_name,
        po.grand_total,
        COALESCE(pay.paid_total, 0) AS supplier_payments,
        GREATEST(0, po.grand_total - COALESCE(pay.paid_total, 0)) AS outstanding_balance
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    LEFT JOIN (
        SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS paid_total
        FROM supplier_payments
        GROUP BY purchase_order_id
    ) pay ON pay.purchase_order_id = po.id
    WHERE DATE(po.created_at) BETWEEN :start AND :end
    ORDER BY po.created_at DESC, po.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':start' => $start, ':end' => $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ap_reconciliation_' . $start . '_' . $end . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['PO Number', 'Supplier', 'PO Total', 'Supplier Payments', 'Outstanding Balance']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['po_number'], $r['supplier_name'], number_format((float)$r['grand_total'], 2), number_format((float)$r['supplier_payments'], 2), number_format((float)$r['outstanding_balance'], 2)]);
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
                    <h2 class="mb-1 fw-bold">Accounts Payable Reconciliation</h2>
                    <div class="avm-muted">Purchase order settlement and supplier payments.</div>
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
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th class="text-end">PO Total</th>
                                    <th class="text-end">Supplier Payments</th>
                                    <th class="text-end">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="5" class="text-center py-4 avm-muted">No purchase orders found for the selected range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r):
                                        $grand = (float)$r['grand_total'];
                                        $paid = (float)$r['supplier_payments'];
                                        $balance = max(0, round($grand - $paid, 2));
                                        // Color: green reconciled, yellow partial, red outstanding
                                        if ($balance <= 0.01) {
                                            $rowClass = 'table-success';
                                        } elseif ($paid > 0) {
                                            $rowClass = 'table-warning';
                                        } else {
                                            $rowClass = 'table-danger';
                                        }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><a href="<?= APP_BASE ?>/purchases/view.php?id=<?= (int)$r['purchase_order_id'] ?>"><?= htmlspecialchars($r['po_number']) ?></a></td>
                                        <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                                        <td class="text-end"><?= format_inr($grand) ?></td>
                                        <td class="text-end"><?= format_inr($paid) ?></td>
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
