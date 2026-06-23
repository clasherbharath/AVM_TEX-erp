<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Stock Audit Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$auditRows = [];
$dbError = '';
$mismatchCount = 0;
$matchedCount = 0;

try {
    $stmt = $pdo->query(
        'SELECT i.id AS product_id,
                i.product_name,
                i.category,
                i.quantity AS current_quantity,
                i.unit,
                COALESCE(SUM(sm.quantity_changed), 0) AS ledger_quantity,
                MAX(sm.created_at) AS last_movement
         FROM inventory i
         LEFT JOIN stock_movements sm ON sm.product_id = i.id
         GROUP BY i.id, i.product_name, i.category, i.quantity, i.unit
         ORDER BY i.product_name ASC'
    );
    $auditRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($auditRows as $row) {
        $current = (float)$row['current_quantity'];
        $ledger = (float)$row['ledger_quantity'];
        if (abs($current - $ledger) <= 0.01) {
            $matchedCount++;
        } else {
            $mismatchCount++;
        }
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load stock audit data.';
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
                    <h2 class="mb-1 fw-bold">Stock Audit Report</h2>
                    <div class="avm-muted">Compare current inventory against the stock movement ledger.</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small text-muted">Matched Items</div>
                        <div class="h4 mb-0 fw-bold text-success"><?= $matchedCount ?></div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small text-muted">Mismatch Items</div>
                        <div class="h4 mb-0 fw-bold text-danger"><?= $mismatchCount ?></div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small text-muted">Total Products</div>
                        <div class="h4 mb-0 fw-bold"><?= count($auditRows) ?></div>
                    </div></div>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Qty</th>
                                <th>Ledger Qty</th>
                                <th>Variance</th>
                                <th>Last Movement</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($auditRows) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No inventory items found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auditRows as $row): ?>
                                    <?php
                                    $current = (float)$row['current_quantity'];
                                    $ledger = (float)$row['ledger_quantity'];
                                    $variance = round($current - $ledger, 2);
                                    $ok = abs($variance) <= 0.01;
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars((string)$row['product_name']) ?></td>
                                        <td><?= htmlspecialchars((string)$row['category']) ?></td>
                                        <td><?= number_format($current, 2) ?></td>
                                        <td><?= number_format($ledger, 2) ?></td>
                                        <td class="fw-semibold <?= $ok ? 'text-success' : 'text-danger' ?>"><?= number_format($variance, 2) ?></td>
                                        <td><?= !empty($row['last_movement']) ? htmlspecialchars(date('d M Y, h:i A', strtotime((string)$row['last_movement']))) : '—' ?></td>
                                        <td>
                                            <span class="badge <?= $ok ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $ok ? 'Balanced' : 'Mismatch' ?>
                                            </span>
                                        </td>
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
