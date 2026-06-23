<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';
require_once __DIR__ . '/../helpers/procurement.php';

$pageTitle = 'Accounts Payable • A.V.M TEX ERP';
$activeMenu = 'Reports';

$rows = [];
$dbError = '';
$totalOutstanding = 0.0;

try {
    $baseRows = $pdo->query('SELECT s.id, s.supplier_name, s.phone, s.payment_terms FROM suppliers s ORDER BY s.supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = [];

    foreach ($baseRows as $row) {
        $summary = getSupplierBalanceSummary($pdo, (int)$row['id']);
        if ((float)$summary['balance_due'] > 0.01) {
            $row['summary'] = $summary;
            $rows[] = $row;
            $totalOutstanding += (float)$summary['balance_due'];
        }
    }

    usort($rows, static function (array $left, array $right): int {
        $leftBalance = (float)($left['summary']['balance_due'] ?? 0);
        $rightBalance = (float)($right['summary']['balance_due'] ?? 0);
        return $rightBalance <=> $leftBalance;
    });
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load payable report.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid"><div class="row">
    <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
    <main class="col-12 col-lg-9 col-xl-10 avm-content">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h2 class="mb-1 fw-bold">Accounts Payable</h2>
                <div class="avm-muted">Outstanding supplier liabilities</div>
            </div>
            <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>

        <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
        <?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-lg-4"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Suppliers with Due</div><div class="h4 mb-0 fw-bold text-danger"><?= count($rows) ?></div></div></div></div>
            <div class="col-md-6 col-lg-4"><div class="card avm-card h-100"><div class="card-body"><div class="small text-muted">Total Outstanding</div><div class="h4 mb-0 fw-bold text-danger"><?= format_inr($totalOutstanding) ?></div></div></div></div>
        </div>

        <div class="card avm-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Supplier</th><th>Phone</th><th>Terms</th><th>Outstanding</th></tr></thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No outstanding payables found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $summary = $row['summary']; ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['payment_terms'] ?? '—') ?></td>
                                    <td class="fw-semibold text-danger"><?= format_inr((float)$summary['balance_due']) ?></td>
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
