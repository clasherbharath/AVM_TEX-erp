<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Customers • A.V.M TEX ERP System';
$activeMenu = 'Customers';

$search = trim((string)($_GET['q'] ?? ''));
$customers = [];

$selectCols = 'id, customer_name, phone, gst_number, email, address, city, state, pincode, created_at';

try {
    if ($search === '') {
        $stmt = $pdo->query(
            "SELECT {$selectCols} FROM customers ORDER BY created_at DESC"
        );
        $customers = $stmt->fetchAll();
    } else {
        // Each LIKE needs its own placeholder (PDO native prepares cannot reuse :q).
        $like = '%' . $search . '%';

        $stmt = $pdo->prepare(
            "SELECT {$selectCols}
             FROM customers
             WHERE customer_name LIKE :q_name
                OR phone LIKE :q_phone
                OR COALESCE(email, '') LIKE :q_email
                OR address LIKE :q_address
                OR COALESCE(city, '') LIKE :q_city
                OR COALESCE(gst_number, '') LIKE :q_gst
             ORDER BY created_at DESC"
        );

        $stmt->execute([
            ':q_name'    => $like,
            ':q_phone'   => $like,
            ':q_email'   => $like,
            ':q_address' => $like,
            ':q_city'    => $like,
            ':q_gst'     => $like,
        ]);

        $customers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG
        ? 'Could not load customers: ' . $e->getMessage()
        : 'Could not load customers. Please check the customers table.';
    $customers = [];
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
        <h2 class="mb-1 fw-bold">Customer Management</h2>
        <div class="avm-muted">Manage textile business customers — add, edit, search and delete.</div>
    </div>
    <a href="<?= APP_BASE ?>/customers/add.php" class="btn btn-avm-gold">
        + Add Customer
    </a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

<div class="card avm-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= APP_BASE ?>/customers/index.php" class="row g-2 align-items-end">
            <div class="col-12 col-md-8 col-lg-9">
                <label for="q" class="form-label">Search customers</label>
                <input
                    type="search"
                    name="q"
                    id="q"
                    class="form-control"
                    placeholder="Search by name, phone, email, address, city, GST..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>
            <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-avm-green flex-grow-1">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card avm-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">Customer List</div>
            <span class="badge avm-badge-count"><?= count($customers) ?> record(s)</span>
        </div>

        <?php if (count($customers) === 0): ?>
            <div class="text-center py-5 avm-muted">
                <div class="fs-5 mb-2">No customers found</div>
                <?php if ($search !== ''): ?>
                    <p class="mb-3">No results for &ldquo;<?= htmlspecialchars($search) ?>&rdquo;.</p>
                    <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-outline-secondary btn-sm">View all</a>
                <?php else: ?>
                    <p class="mb-3">Start by adding your first customer.</p>
                    <a href="<?= APP_BASE ?>/customers/add.php" class="btn btn-avm-gold btn-sm">Add Customer</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle avm-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th class="d-none d-lg-table-cell">Address</th>
                            <th class="d-none d-md-table-cell">GST</th>
                            <th class="d-none d-sm-table-cell">Created At</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $i => $row): ?>
                            <tr>
                                <td class="avm-muted"><?= $i + 1 ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['customer_name']) ?></div>
                                    <div class="small avm-muted d-lg-none">
                                        <?= htmlspecialchars(trim(($row['city'] ?? '') . ', ' . ($row['state'] ?? ''), ', ')) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="tel:<?= htmlspecialchars($row['phone']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($row['phone']) ?>
                                    </a>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($row['email'])): ?>
                                        <?= htmlspecialchars($row['email']) ?>
                                    <?php else: ?>
                                        <span class="avm-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($row['address']) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($row['gst_number'])): ?>
                                        <span class="badge avm-gst-badge"><?= htmlspecialchars($row['gst_number']) ?></span>
                                    <?php else: ?>
                                        <span class="avm-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-sm-table-cell small avm-muted">
                                    <?= htmlspecialchars(date('d M Y', strtotime((string)$row['created_at']))) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_BASE ?>/customers/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-dark">Edit</a>
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-delete-customer"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteCustomerModal"
                                            data-id="<?= (int)$row['id'] ?>"
                                            data-name="<?= htmlspecialchars($row['customer_name'], ENT_QUOTES) ?>"
                                        >
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

<div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-labelledby="deleteCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content avm-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="deleteCustomerModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete this customer?</p>
                <p class="mb-0 fw-semibold text-danger" id="deleteCustomerName"></p>
                <p class="small avm-muted mt-2 mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?= APP_BASE ?>/customers/delete.php" id="deleteCustomerForm">
                    <input type="hidden" name="id" id="deleteCustomerId" value="">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

        </main>
    </div>
</div>

<style>
@media (min-width: 992px){
  #avmSidebar.offcanvas-lg{
    position: fixed;
    top: 56px;
    bottom: 0;
    left: 0;
    transform: none;
    visibility: visible !important;
  }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
