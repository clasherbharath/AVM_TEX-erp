<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';
require_once __DIR__ . '/../helpers/procurement.php';

$pageTitle = 'Supplier Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$search = trim((string)($_GET['q'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$suppliers = [];
$reportRows = [];
$dbError = '';

try {
    $suppliers = $pdo->query('SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = 'SELECT s.id, s.supplier_name, s.contact_person, s.phone, s.city, s.state,
                   COUNT(DISTINCT po.id) AS po_count
            FROM suppliers s
            LEFT JOIN purchase_orders po ON po.supplier_id = s.id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (s.supplier_name LIKE :q_name OR s.contact_person LIKE :q_contact OR s.phone LIKE :q_phone OR COALESCE(s.city, "") LIKE :q_city OR COALESCE(s.state, "") LIKE :q_state)';
        $like = '%' . $search . '%';
        $params[':q_name'] = $like;
        $params[':q_contact'] = $like;
        $params[':q_phone'] = $like;
        $params[':q_city'] = $like;
        $params[':q_state'] = $like;
    }

    if ($supplierId > 0) {
        $sql .= ' AND s.id = :supplier_id';
        $params[':supplier_id'] = $supplierId;
    }

    $sql .= ' GROUP BY s.id ORDER BY s.supplier_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load supplier report.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid"><div class="row">
    <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
    <main class="col-12 col-lg-9 col-xl-10 avm-content">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h2 class="mb-1 fw-bold">Supplier Report</h2>
                <div class="avm-muted">Supplier purchases, payments, and outstanding balances</div>
            </div>
            <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>

        <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
        <?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>

        <div class="card avm-card mb-3"><div class="card-body">
            <form method="get" action="<?= APP_BASE ?>/reports/supplier_report.php" class="row g-2 align-items-end">
                <div class="col-12 col-md-4"><label class="form-label small fw-semibold">Search</label><input type="search" name="q" class="form-control" placeholder="Supplier, contact, phone..." value="<?= htmlspecialchars($search) ?>"></div>
                <div class="col-12 col-md-5"><label class="form-label small fw-semibold">Supplier</label><select name="supplier_id" class="form-select"><option value="">All</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $supplierId === (int)$supplier['id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['supplier_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-3"><button type="submit" class="btn btn-avm-gold w-100">Generate Report</button></div>
            </form>
        </div></div>

        <div class="card avm-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Supplier</th><th>Phone</th><th>POs</th><th>Purchase</th><th>Paid</th><th>Balance Due</th></tr></thead>
                    <tbody>
                        <?php if (count($reportRows) === 0): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No supplier data found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): ?>
                                <?php $summary = getSupplierBalanceSummary($pdo, (int)$row['id']); ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= (int)$row['po_count'] ?></td>
                                    <td><?= format_inr($summary['total_purchase']) ?></td>
                                    <td><?= format_inr($summary['total_paid']) ?></td>
                                    <td class="fw-semibold text-danger"><?= format_inr($summary['balance_due']) ?></td>
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
