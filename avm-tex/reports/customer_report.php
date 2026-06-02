<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Customer Report • A.V.M TEX ERP';
$activeMenu = 'Reports';

$search = trim((string)($_GET['q'] ?? ''));
$customerData = [];
$dbError = '';

try {
    $sql = "SELECT c.id, c.customer_name, c.phone, c.email, c.gst_number,
                   COUNT(DISTINCT i.id) AS invoice_count,
                   COALESCE(SUM(i.grand_total), 0) AS total_purchase,
                   MAX(i.invoice_date) AS last_purchase
            FROM customers c
            LEFT JOIN invoices i ON c.id = i.customer_id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (c.customer_name LIKE :search_name OR c.phone LIKE :search_phone OR c.email LIKE :search_email OR c.gst_number LIKE :search_gst)";
        $params[':search_name'] = '%' . $search . '%';
        $params[':search_phone'] = '%' . $search . '%';
        $params[':search_email'] = '%' . $search . '%';
        $params[':search_gst'] = '%' . $search . '%';
    }

    $sql .= " GROUP BY c.id ORDER BY total_purchase DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customerData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load customer data.';
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
                    <h2 class="mb-1 fw-bold">Customer Report</h2>
                    <div class="avm-muted">Customer purchase history and metrics</div>
                </div>
                <a href="<?= APP_BASE ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/reports/customer_report.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <label for="searchInput" class="form-label small fw-semibold">Search</label>
                            <input type="search" id="searchInput" name="q" class="form-control"
                                   placeholder="Customer name, phone, email, GST"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="submit" class="btn btn-avm-gold w-100">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Invoices</th>
                                <th>Total Purchase</th>
                                <th>Last Purchase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customerData) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No customers found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customerData as $customer): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($customer['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($customer['phone'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($customer['email'] ?? '—') ?></td>
                                        <td><?= (int)$customer['invoice_count'] ?></td>
                                        <td class="fw-semibold text-success"><?= format_inr((float)$customer['total_purchase']) ?></td>
                                        <td><?= htmlspecialchars($customer['last_purchase'] ? date('d M Y', strtotime($customer['last_purchase'])) : '—') ?></td>
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
