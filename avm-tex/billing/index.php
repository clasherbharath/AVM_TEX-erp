<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

$pageTitle = 'Billing • A.V.M TEX ERP';
$activeMenu = 'Billing';

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$customerFilter = (int)($_GET['customer_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$invoiceRows = [];
$customerList = [];
$dbError = '';
$stats = ['count' => 0, 'sales' => 0.0, 'paid' => 0, 'pending' => 0];

try {
    $customerList = $pdo->query(
        'SELECT id, customer_name FROM customers ORDER BY customer_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = 'SELECT i.id, i.invoice_number, i.invoice_date, i.subtotal, i.discount,
                   i.gst_total, i.grand_total, i.status, i.created_at,
                   c.customer_name
            FROM invoices i
            INNER JOIN customers c ON c.id = i.customer_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (i.invoice_number LIKE :q_num OR c.customer_name LIKE :q_cust)';
        $like = '%' . $search . '%';
        $params[':q_num'] = $like;
        $params[':q_cust'] = $like;
    }
    if ($statusFilter !== '' && in_array($statusFilter, ['paid', 'pending', 'cancelled'], true)) {
        $sql .= ' AND i.status = :status';
        $params[':status'] = $statusFilter;
    }
    if ($customerFilter > 0) {
        $sql .= ' AND i.customer_id = :customer_id';
        $params[':customer_id'] = $customerFilter;
    }
    if ($dateFrom !== '' && strtotime($dateFrom)) {
        $sql .= ' AND i.invoice_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '' && strtotime($dateTo)) {
        $sql .= ' AND i.invoice_date <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sql .= ' ORDER BY i.invoice_date DESC, i.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoiceRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $statRow = $pdo->query(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(grand_total), 0) AS sales,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_cnt,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt
         FROM invoices"
    )->fetch(PDO::FETCH_ASSOC);

    if (is_array($statRow)) {
        $stats['count'] = (int)($statRow['cnt'] ?? 0);
        $stats['sales'] = (float)($statRow['sales'] ?? 0);
        $stats['paid'] = (int)($statRow['paid_cnt'] ?? 0);
        $stats['pending'] = (int)($statRow['pending_cnt'] ?? 0);
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG
        ? $e->getMessage()
        : 'Billing tables not found. Import sql/invoices.sql and sql/invoice_items.sql';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$statusBadge = static function (string $status): string {
    return match ($status) {
        'paid' => 'bg-success',
        'cancelled' => 'bg-secondary',
        default => 'bg-warning text-dark',
    };
};
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="d-flex align-items-start justify-content-between flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Billing & Invoices</h2>
                    <div class="avm-muted">Textile ERP invoicing with inventory integration.</div>
                </div>
                <a href="<?= APP_BASE ?>/billing/create.php" class="btn btn-avm-gold">+ Create Invoice</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small avm-muted">Total Invoices</div>
                        <div class="h4 avm-metric mb-0"><?= $stats['count'] ?></div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small avm-muted">Total Sales</div>
                        <div class="h4 avm-metric mb-0">₹ <?= number_format($stats['sales'], 2) ?></div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small avm-muted">Paid</div>
                        <div class="h4 avm-metric mb-0 text-success"><?= $stats['paid'] ?></div>
                    </div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card avm-card h-100"><div class="card-body">
                        <div class="small avm-muted">Pending</div>
                        <div class="h4 avm-metric mb-0 text-warning"><?= $stats['pending'] ?></div>
                    </div></div>
                </div>
            </div>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/billing/index.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Search</label>
                            <input type="search" name="q" class="form-control" placeholder="Invoice # or customer"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($customerList as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $customerFilter === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['customer_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-12 col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-avm-green w-100">Go</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="card-body">
                    <div class="fw-semibold mb-3">Invoice List (<?= count($invoiceRows) ?>)</div>

                    <?php if ($dbError === '' && count($invoiceRows) === 0): ?>
                        <div class="text-center py-5 avm-muted">
                            <div class="fs-5 mb-2">No invoices found</div>
                            <a href="<?= APP_BASE ?>/billing/create.php" class="btn btn-avm-gold btn-sm">Create Invoice</a>
                        </div>
                    <?php elseif ($dbError === ''): ?>
                        <div class="table-responsive">
                            <table class="table table-hover avm-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Subtotal</th>
                                        <th>GST</th>
                                        <th>Grand Total</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoiceRows as $inv): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                            <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                            <td><?= htmlspecialchars(date('d M Y', strtotime($inv['invoice_date']))) ?></td>
                                            <td>₹ <?= number_format((float)$inv['subtotal'], 2) ?></td>
                                            <td>₹ <?= number_format((float)$inv['gst_total'], 2) ?></td>
                                            <td class="fw-semibold">₹ <?= number_format((float)$inv['grand_total'], 2) ?></td>
                                            <td>
                                                <span class="badge <?= $statusBadge($inv['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($inv['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= APP_BASE ?>/billing/invoice_view.php?id=<?= (int)$inv['id'] ?>" class="btn btn-outline-dark">View</a>
                                                    <a href="<?= APP_BASE ?>/billing/print_invoice.php?id=<?= (int)$inv['id'] ?>" class="btn btn-outline-secondary" target="_blank">Print</a>
                                                    <button type="button" class="btn btn-outline-danger"
                                                            data-bs-toggle="modal" data-bs-target="#deleteInvoiceModal"
                                                            data-id="<?= (int)$inv['id'] ?>"
                                                            data-name="<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>">
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

            <div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content avm-modal">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Delete Invoice</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Delete invoice <strong id="deleteInvoiceName"></strong>? Stock will be restored.</p>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" action="<?= APP_BASE ?>/billing/delete_invoice.php">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="id" id="deleteInvoiceId">
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
document.getElementById('deleteInvoiceModal')?.addEventListener('show.bs.modal', (e) => {
  const b = e.relatedTarget;
  if (!b) return;
  document.getElementById('deleteInvoiceId').value = b.getAttribute('data-id') || '';
  document.getElementById('deleteInvoiceName').textContent = b.getAttribute('data-name') || '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
