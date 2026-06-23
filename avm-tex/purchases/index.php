<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Purchases • A.V.M TEX ERP';
$activeMenu = 'Purchases';

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$supplierFilter = (int)($_GET['supplier_id'] ?? 0);
$purchaseRows = [];
$suppliers = [];
$dbError = '';
$stats = ['count' => 0, 'spent' => 0.0, 'paid' => 0.0, 'due' => 0.0];

try {
    $suppliers = $pdo->query('SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = 'SELECT po.id, po.po_number, po.order_date, po.expected_date, po.status, po.payment_status,
                   po.grand_total, po.created_at, s.supplier_name,
                   COALESCE(pay.total_paid, 0) AS total_paid
            FROM purchase_orders po
            INNER JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN (
                SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS total_paid
                FROM supplier_payments
                GROUP BY purchase_order_id
            ) pay ON pay.purchase_order_id = po.id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (po.po_number LIKE :q_po OR s.supplier_name LIKE :q_supplier)';
        $like = '%' . $search . '%';
        $params[':q_po'] = $like;
        $params[':q_supplier'] = $like;
    }
    if ($statusFilter !== '' && in_array($statusFilter, ['draft', 'ordered', 'partial', 'received', 'cancelled'], true)) {
        $sql .= ' AND po.status = :status';
        $params[':status'] = $statusFilter;
    }
    if ($supplierFilter > 0) {
        $sql .= ' AND po.supplier_id = :supplier_id';
        $params[':supplier_id'] = $supplierFilter;
    }

    $sql .= ' ORDER BY po.order_date DESC, po.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchaseRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $statsRow = $pdo->query(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(grand_total), 0) AS spent,
                COALESCE(SUM(payment_status = 'paid'), 0) AS paid_count
         FROM purchase_orders"
    )->fetch(PDO::FETCH_ASSOC);
    if (is_array($statsRow)) {
        $stats['count'] = (int)($statsRow['cnt'] ?? 0);
        $stats['spent'] = (float)($statsRow['spent'] ?? 0);
    }

    $balanceRow = $pdo->query(
        "SELECT COALESCE(SUM(grand_total), 0) AS spent,
                COALESCE((SELECT SUM(amount) FROM supplier_payments), 0) AS paid
         FROM purchase_orders"
    )->fetch(PDO::FETCH_ASSOC);
    if (is_array($balanceRow)) {
        $stats['paid'] = (float)($balanceRow['paid'] ?? 0);
        $stats['due'] = max(0, (float)($balanceRow['spent'] ?? 0) - $stats['paid']);
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load purchases.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Procurement & Purchases</h2>
                    <div class="avm-muted">Manage purchase orders, receipts, and supplier payments.</div>
                </div>
                <a href="<?= APP_BASE ?>/purchases/add.php" class="btn btn-avm-gold">+ New Purchase Order</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Purchase Orders</div><div class="h4 avm-metric mb-0"><?= $stats['count'] ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Total Spend</div><div class="h4 avm-metric mb-0">₹ <?= number_format($stats['spent'], 2) ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Paid</div><div class="h4 avm-metric mb-0 text-success">₹ <?= number_format($stats['paid'], 2) ?></div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Outstanding</div><div class="h4 avm-metric mb-0 text-danger">₹ <?= number_format($stats['due'], 2) ?></div></div></div></div>
            </div>

            <div class="card avm-card mb-3"><div class="card-body">
                <form method="get" action="<?= APP_BASE ?>/purchases/index.php" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4"><label class="form-label">Search</label><input type="search" name="q" class="form-control" placeholder="PO # or supplier" value="<?= htmlspecialchars($search) ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option><option value="ordered" <?= $statusFilter === 'ordered' ? 'selected' : '' ?>>Ordered</option><option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option><option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option><option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select></div>
                    <div class="col-6 col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $supplierFilter === (int)$supplier['id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['supplier_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 col-md-2 d-flex gap-2"><button type="submit" class="btn btn-avm-green flex-grow-1">Go</button><?php if ($search !== '' || $statusFilter !== '' || $supplierFilter > 0): ?><a href="<?= APP_BASE ?>/purchases/index.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?></div>
                </form>
            </div></div>

            <div class="card avm-card"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3"><div class="fw-semibold">Purchase Orders</div><span class="badge avm-badge-count"><?= count($purchaseRows) ?> record(s)</span></div>
                <?php if (count($purchaseRows) === 0): ?>
                    <div class="text-center py-5 avm-muted"><div class="fs-5 mb-2">No purchase orders found</div><a href="<?= APP_BASE ?>/purchases/add.php" class="btn btn-avm-gold btn-sm">Create Purchase Order</a></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle avm-table mb-0">
                            <thead><tr><th>PO #</th><th>Supplier</th><th>Date</th><th>Total</th><th>Paid</th><th>Status</th><th>Payment</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($purchaseRows as $row): ?>
                                    <?php $balance = max(0, (float)$row['grand_total'] - (float)$row['total_paid']); ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['po_number']) ?></td>
                                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime((string)$row['order_date']))) ?></td>
                                        <td>₹ <?= number_format((float)$row['grand_total'], 2) ?></td>
                                        <td>₹ <?= number_format((float)$row['total_paid'], 2) ?></td>
                                        <td><span class="badge bg-<?= $row['status'] === 'received' ? 'success' : ($row['status'] === 'cancelled' ? 'secondary' : ($row['status'] === 'partial' ? 'warning text-dark' : 'primary')) ?>"><?= htmlspecialchars(ucfirst((string)$row['status'])) ?></span></td>
                                        <td><?= $balance <= 0.01 ? '<span class="badge bg-success">Paid</span>' : ($row['total_paid'] > 0 ? '<span class="badge bg-warning text-dark">Partial</span>' : '<span class="badge bg-danger">Unpaid</span>') ?></td>
                                        <td class="text-end"><div class="btn-group btn-group-sm"><a href="<?= APP_BASE ?>/purchases/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-dark">View</a><a href="<?= APP_BASE ?>/purchases/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary">Edit</a></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div></div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
