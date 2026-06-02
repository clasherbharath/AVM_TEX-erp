<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_validation.php';

$pageTitle = 'Edit Customer • A.V.M TEX ERP System';
$activeMenu = 'Customers';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid customer selected.';
    header('Location: ' . APP_BASE . '/customers/index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, customer_name, phone, gst_number, email, address, city, state, pincode, created_at
     FROM customers WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['flash_error'] = 'Customer not found.';
    header('Location: ' . APP_BASE . '/customers/index.php');
    exit;
}

$form = customerFormFromSource($customer);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = customerFormFromSource($_POST);
    $errors = validateCustomerInput($form);

    if ($errors === []) {
        $data = normalizeCustomerInput($form);

        try {
            $update = $pdo->prepare(
                'UPDATE customers SET
                    customer_name = :customer_name,
                    phone = :phone,
                    gst_number = :gst_number,
                    email = :email,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode
                 WHERE id = :id'
            );
            $update->execute([
                ':customer_name' => $data['customer_name'],
                ':phone' => $data['phone'],
                ':gst_number' => $data['gst_number'],
                ':email' => $data['email'],
                ':address' => $data['address'],
                ':city' => $data['city'],
                ':state' => $data['state'],
                ':pincode' => $data['pincode'],
                ':id' => $id,
            ]);

            $_SESSION['flash_success'] = 'Customer updated successfully.';
            header('Location: ' . APP_BASE . '/customers/index.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to update customer. Please try again.';
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
        <h2 class="mb-1 fw-bold">Edit Customer</h2>
        <div class="avm-muted">
            Created on <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$customer['created_at']))) ?>
        </div>
    </div>
    <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-outline-secondary btn-sm">← Back to list</a>
</div>

<?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>

<div class="card avm-card">
    <div class="card-body">
        <?php
        $formAction = APP_BASE . '/customers/edit.php';
        $submitLabel = 'Update Customer';
        $hiddenId = $id;
        require __DIR__ . '/_form.php';
        ?>
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
