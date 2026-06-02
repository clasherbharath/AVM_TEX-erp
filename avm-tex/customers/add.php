<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_validation.php';

$pageTitle = 'Add Customer • A.V.M TEX ERP System';
$activeMenu = 'Customers';

$errors = [];
$form = emptyCustomerForm();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = customerFormFromSource($_POST);
    $errors = validateCustomerInput($form);

    if ($errors === []) {
        $data = normalizeCustomerInput($form);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO customers (
                    customer_name, phone, gst_number, email,
                    address, city, state, pincode
                ) VALUES (
                    :customer_name, :phone, :gst_number, :email,
                    :address, :city, :state, :pincode
                )'
            );
            $stmt->execute([
                ':customer_name' => $data['customer_name'],
                ':phone' => $data['phone'],
                ':gst_number' => $data['gst_number'],
                ':email' => $data['email'],
                ':address' => $data['address'],
                ':city' => $data['city'],
                ':state' => $data['state'],
                ':pincode' => $data['pincode'],
            ]);

            $_SESSION['flash_success'] = 'Customer added successfully.';
            header('Location: ' . APP_BASE . '/customers/index.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to save customer. Please check database connection and table.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2 class="mb-1 fw-bold">Add Customer</h2>
        <div class="avm-muted">Register a new textile business customer.</div>
    </div>
    <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-outline-secondary btn-sm">← Back to list</a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>

<div class="card avm-card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
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
