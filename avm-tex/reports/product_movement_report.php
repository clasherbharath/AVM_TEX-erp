<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Product Movement Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$productId = (int)($_GET['product_id'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$productList = [];
$movementRows = [];
$dbError = '';

try {
    $productList = $pdo->query(
        'SELECT id, product_name FROM inventory ORDER BY product_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = 'SELECT sm.product_id,
                   COALESCE(i.product_name, CONCAT("Deleted Item #", sm.product_id)) AS product_name,
                   COALESCE(i.unit, "—") AS unit,
                   COUNT(*) AS movement_count,
                   COALESCE(SUM(CASE WHEN sm.quantity_changed > 0 THEN sm.quantity_changed ELSE 0 END), 0) AS qty_in,
                   COALESCE(SUM(CASE WHEN sm.quantity_changed < 0 THEN ABS(sm.quantity_changed) ELSE 0 END), 0) AS qty_out,
                   COALESCE(SUM(sm.quantity_changed), 0) AS net_change,
                   MAX(sm.created_at) AS last_movement
            FROM stock_movements sm
            LEFT JOIN inventory i ON i.id = sm.product_id
            WHERE 1=1';
    $params = [];

    if ($productId > 0) {
        $sql .= ' AND sm.product_id = :product_id';
        $params[':product_id'] = $productId;
    }

    if ($search !== '') {
        $sql .= ' AND (i.product_name LIKE :search OR sm.notes LIKE :notes OR sm.reference_type LIKE :reference_type)';
        $like = '%' . $search . '%';
        $params[':search'] = $like;
        $params[':notes'] = $like;
        $params[':reference_type'] = $like;
    }

    if ($dateFrom !== '' && strtotime($dateFrom)) {
        $sql .= ' AND DATE(sm.created_at) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '' && strtotime($dateTo)) {
        $sql .= ' AND DATE(sm.created_at) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sql .= ' GROUP BY sm.product_id, i.product_name, i.unit ORDER BY last_movement DESC, product_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movementRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load product movement data.';
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
                    <h2 class="mb-1 fw-bold">Product Movement Report</h2>
                    <div class="avm-muted">Movement totals per product across the stock ledger.</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/product_movement_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="0">All Products</option>
                                <?php foreach ($productList as $product): ?>
                                    <option value="<?= (int)$product['id'] ?>" <?= $productId === (int)$product['id'] ? 'selected' : '' ?>><?= htmlspecialchars($product['product_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold">Search</label>
                            <input type="search" name="q" class="form-control" placeholder="Product or note" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold">From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold">To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-12">
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
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Movements</th>
                                <th>Qty In</th>
                                <th>Qty Out</th>
                                <th>Net Change</th>
                                <th>Last Movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($movementRows) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No product movement data found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movementRows as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars((string)$row['product_name']) ?></td>
                                        <td><?= htmlspecialchars((string)$row['unit']) ?></td>
                                        <td><?= (int)$row['movement_count'] ?></td>
                                        <td class="text-success fw-semibold"><?= number_format((float)$row['qty_in'], 2) ?></td>
                                        <td class="text-danger fw-semibold"><?= number_format((float)$row['qty_out'], 2) ?></td>
                                        <td class="fw-semibold"><?= number_format((float)$row['net_change'], 2) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$row['last_movement']))) ?></td>
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
