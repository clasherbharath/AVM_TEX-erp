<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/format_currency.php';
require_once __DIR__ . '/../helpers/procurement.php';

$pageTitle = 'Suppliers • A.V.M TEX ERP';
$activeMenu = 'Suppliers';

$search = trim((string)($_GET['q'] ?? ''));
$suppliers = [];
$dbError = '';

try {
    $sql = 'SELECT s.id, s.supplier_name, s.contact_person, s.phone, s.email, s.gst_number,
                   s.city, s.state, s.created_at
            FROM suppliers s
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (s.supplier_name LIKE :q_name OR s.contact_person LIKE :q_contact OR s.phone LIKE :q_phone OR COALESCE(s.email, "") LIKE :q_email OR COALESCE(s.gst_number, "") LIKE :q_gst OR COALESCE(s.city, "") LIKE :q_city OR COALESCE(s.state, "") LIKE :q_state)';
        $like = '%' . $search . '%';
        $params = [
            ':q_name' => $like,
            ':q_contact' => $like,
            ':q_phone' => $like,
            ':q_email' => $like,
            ':q_gst' => $like,
            ':q_city' => $like,
            ':q_state' => $like,
        ];
    }

    $sql .= ' ORDER BY s.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load suppliers.';
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
                    <h2 class="mb-1 fw-bold">Supplier Management</h2>
                    <div class="avm-muted">Manage textile vendors and supplier balances.</div>
                </div>
                <a href="<?= APP_BASE ?>/suppliers/add.php" class="btn btn-avm-gold">+ Add Supplier</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/suppliers/index.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-8 col-lg-9">
                            <label for="q" class="form-label">Search suppliers</label>
                            <input type="search" id="q" name="q" class="form-control" placeholder="Name, contact, phone, GST, city..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-avm-green flex-grow-1">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="<?= APP_BASE ?>/suppliers/index.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-semibold">Supplier List</div>
                        <span class="badge avm-badge-count"><?= count($suppliers) ?> record(s)</span>
                    </div>

                    <?php if (count($suppliers) === 0): ?>
                        <div class="text-center py-5 avm-muted">
                            <div class="fs-5 mb-2">No suppliers found</div>
                            <?php if ($search !== ''): ?>
                                <p class="mb-3">No results for &ldquo;<?= htmlspecialchars($search) ?>&rdquo;.</p>
                                <a href="<?= APP_BASE ?>/suppliers/index.php" class="btn btn-outline-secondary btn-sm">View all</a>
                            <?php else: ?>
                                <p class="mb-3">Start by adding your first supplier.</p>
                                <a href="<?= APP_BASE ?>/suppliers/add.php" class="btn btn-avm-gold btn-sm">Add Supplier</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle avm-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Supplier</th>
                                        <th>Phone</th>
                                        <th class="d-none d-md-table-cell">City</th>
                                        <th class="d-none d-lg-table-cell">Balance Due</th>
                                        <th class="d-none d-md-table-cell">Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $i => $row): ?>
                                        <?php $summary = getSupplierBalanceSummary($pdo, (int)$row['id']); ?>
                                        <?php $balanceDue = (float)$summary['balance_due']; ?>
                                        <tr>
                                            <td class="avm-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['supplier_name']) ?></div>
                                                <?php if (!empty($row['contact_person'])): ?>
                                                    <div class="small avm-muted">Contact: <?= htmlspecialchars($row['contact_person']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['phone']) ?></td>
                                            <td class="d-none d-md-table-cell"><?= htmlspecialchars(trim(($row['city'] ?? '') . ', ' . ($row['state'] ?? ''), ', ')) ?: '<span class="avm-muted">—</span>' ?></td>
                                            <td class="d-none d-lg-table-cell fw-semibold <?= $balanceDue > 0 ? 'text-danger' : 'text-success' ?>"><?= format_inr($balanceDue) ?></td>
                                            <td class="d-none d-md-table-cell small avm-muted"><?= htmlspecialchars(date('d M Y', strtotime((string)$row['created_at']))) ?></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm flex-wrap">
                                                    <a href="<?= APP_BASE ?>/suppliers/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-dark">Edit</a>
                                                    <a href="<?= APP_BASE ?>/reports/supplier_report.php?supplier_id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary">Report</a>
                                                    <button type="button" class="btn btn-outline-danger"
                                                            data-bs-toggle="modal" data-bs-target="#deleteSupplierModal"
                                                            data-id="<?= (int)$row['id'] ?>"
                                                            data-name="<?= htmlspecialchars($row['supplier_name'], ENT_QUOTES) ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content avm-modal">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Delete this supplier?</p>
                            <p class="fw-semibold text-danger mb-0" id="deleteSupplierName"></p>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" action="<?= APP_BASE ?>/suppliers/delete.php">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="id" id="deleteSupplierId" value="">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('deleteSupplierModal')?.addEventListener('show.bs.modal', (e) => {
  const b = e.relatedTarget;
  if (!b) return;
  document.getElementById('deleteSupplierId').value = b.getAttribute('data-id') || '';
  document.getElementById('deleteSupplierName').textContent = b.getAttribute('data-name') || '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
