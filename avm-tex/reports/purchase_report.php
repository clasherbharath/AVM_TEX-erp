<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Purchase Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$year = trim((string)($_GET['year'] ?? date('Y')));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$purchaseData = [];
$suppliers = [];
$stats = ['orders' => 0, 'spent' => 0.0, 'received' => 0.0, 'margin' => 0.0];
$dbError = '';

try {
    $suppliers = $pdo->query('SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = "SELECT po.id, po.po_number, po.order_date, po.status, po.grand_total,
                   s.supplier_name,
                   COALESCE(pay.total_paid, 0) AS total_paid,
                   COALESCE(receipts.total_received, 0) AS total_received
            FROM purchase_orders po
            INNER JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN (
                SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS total_paid
                FROM supplier_payments GROUP BY purchase_order_id
            ) pay ON pay.purchase_order_id = po.id
            LEFT JOIN (
                SELECT purchase_order_id, COALESCE(SUM(received_quantity), 0) AS total_received
                FROM purchase_items GROUP BY purchase_order_id
            ) receipts ON receipts.purchase_order_id = po.id
            WHERE YEAR(po.order_date) = :year";
    $params = [':year' => (int)$year];
    if ($supplierId > 0) {
        $sql .= ' AND po.supplier_id = :supplier_id';
        $params[':supplier_id'] = $supplierId;
    }
    $sql .= ' ORDER BY po.order_date DESC, po.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchaseData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($purchaseData as $row) {
        $stats['orders']++;
        $stats['spent'] += (float)$row['grand_total'];
        $stats['received'] += (float)$row['total_received'];
        $stats['margin'] += max(0, (float)$row['grand_total'] - (float)$row['total_paid']);
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load purchase report.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid"><div class="row">
    <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
    <main class="col-12 col-lg-9 col-xl-10 avm-content">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h2 class="mb-1 fw-bold">Purchase Report</h2>
                <div class="avm-muted">Procurement totals and receipt analysis</div>
            </div>
            <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>

        <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
        <?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>

        <div class="card avm-card mb-3"><div class="card-body">
            <form method="get" action="<?= APP_BASE ?>/reports/purchase_report.php" class="row g-2 align-items-end">
                <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Year</label><input type="number" name="year" class="form-control" value="<?= htmlspecialchars($year) ?>" min="2020" max="<?= date('Y') ?>"></div>
                <div class="col-6 col-md-5"><label class="form-label small fw-semibold">Supplier</label><select name="supplier_id" class="form-select"><option value="">All</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $supplierId === (int)$supplier['id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['supplier_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-3"><button type="submit" class="btn btn-avm-gold w-100">Generate Report</button></div>
            </form>
        </div></div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Orders</div><div class="h4 mb-0 fw-bold"><?= $stats['orders'] ?></div></div></div></div>
            <div class="col-md-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Spend</div><div class="h4 mb-0 fw-bold text-success"><?= format_inr($stats['spent']) ?></div></div></div></div>
            <div class="col-md-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Received Qty</div><div class="h4 mb-0 fw-bold"><?= number_format($stats['received'], 2) ?></div></div></div></div>
            <div class="col-md-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Margin Preview</div><div class="h4 mb-0 fw-bold text-primary"><?= format_inr($stats['margin']) ?></div></div></div></div>
        </div>

        <div class="card avm-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>PO #</th><th>Supplier</th><th>Date</th><th>Total</th><th>Received Qty</th><th>Balance</th></tr></thead>
                    <tbody>
                        <?php if (count($purchaseData) === 0): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No purchase data found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($purchaseData as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['po_number']) ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime((string)$row['order_date']))) ?></td>
                                    <td><?= format_inr((float)$row['grand_total']) ?></td>
                                    <td><?= number_format((float)$row['total_received'], 2) ?></td>
                                    <td class="fw-semibold text-danger"><?= format_inr(max(0, (float)$row['grand_total'] - (float)$row['total_paid'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
