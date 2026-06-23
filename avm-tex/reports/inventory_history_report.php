<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Inventory History Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$search = trim((string)($_GET['q'] ?? ''));
$movementType = trim((string)($_GET['movement_type'] ?? ''));
$productId = (int)($_GET['product_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$movementRows = [];
$productList = [];
$dbError = '';

$movementTypes = [
    'initial' => 'Initial',
    'purchase' => 'Purchase',
    'sale' => 'Sale',
    'adjustment' => 'Adjustment',
    'return' => 'Return',
    'delete' => 'Delete',
];

try {
    $productList = $pdo->query(
        'SELECT id, product_name FROM inventory ORDER BY product_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = 'SELECT sm.id, sm.movement_type, sm.product_id, sm.quantity_before,
                   sm.quantity_after, sm.quantity_changed, sm.reference_type,
                   sm.reference_id, sm.notes, sm.created_at,
                   COALESCE(i.product_name, CONCAT("Deleted Item #", sm.product_id)) AS product_name,
                   i.unit
            FROM stock_movements sm
            LEFT JOIN inventory i ON i.id = sm.product_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (i.product_name LIKE :search_name OR sm.reference_type LIKE :search_ref_type OR sm.notes LIKE :search_notes)';
        $like = '%' . $search . '%';
        $params[':search_name'] = $like;
        $params[':search_ref_type'] = $like;
        $params[':search_notes'] = $like;
    }

    if ($movementType !== '' && isset($movementTypes[$movementType])) {
        $sql .= ' AND sm.movement_type = :movement_type';
        $params[':movement_type'] = $movementType;
    }

    if ($productId > 0) {
        $sql .= ' AND sm.product_id = :product_id';
        $params[':product_id'] = $productId;
    }

    if ($dateFrom !== '' && strtotime($dateFrom)) {
        $sql .= ' AND DATE(sm.created_at) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '' && strtotime($dateTo)) {
        $sql .= ' AND DATE(sm.created_at) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sql .= ' ORDER BY sm.created_at DESC, sm.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movementRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load inventory history.';
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
                    <h2 class="mb-1 fw-bold">Inventory History Report</h2>
                    <div class="avm-muted">Chronological stock movement trail across all inventory changes.</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/inventory_history_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold">Search</label>
                            <input type="search" name="q" class="form-control" placeholder="Product, note, reference" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold">Movement Type</label>
                            <select name="movement_type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($movementTypes as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= $movementType === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="0">All Products</option>
                                <?php foreach ($productList as $product): ?>
                                    <option value="<?= (int)$product['id'] ?>" <?= $productId === (int)$product['id'] ? 'selected' : '' ?>><?= htmlspecialchars($product['product_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small fw-semibold">From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small fw-semibold">To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-12 col-md-12">
                            <button type="submit" class="btn btn-avm-gold w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Qty Before</th>
                                <th>Qty After</th>
                                <th>Changed</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($movementRows) === 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No stock movements found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movementRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$row['created_at']))) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars((string)$row['product_name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst((string)$row['movement_type'])) ?></span></td>
                                        <td><?= number_format((float)$row['quantity_before'], 2) ?></td>
                                        <td><?= number_format((float)$row['quantity_after'], 2) ?></td>
                                        <td class="fw-semibold <?= (float)$row['quantity_changed'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= (float)$row['quantity_changed'] >= 0 ? '+' : '' ?><?= number_format((float)$row['quantity_changed'], 2) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars((string)$row['reference_type']) ?>
                                            <?php if (!empty($row['reference_id'])): ?>
                                                #<?= (int)$row['reference_id'] ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></td>
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
