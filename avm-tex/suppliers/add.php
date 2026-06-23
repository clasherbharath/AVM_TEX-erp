<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/supplier_validation.php';
require_once __DIR__ . '/../includes/security.php';

$pageTitle = 'Add Supplier • A.V.M TEX ERP';
$activeMenu = 'Suppliers';

$errors = [];
$form = emptySupplierForm();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/suppliers/add.php');

    $form = supplierFormFromSource($_POST);
    $errors = validateSupplierInput($form);

    if ($errors === []) {
        $data = normalizeSupplierInput($form);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO suppliers (
                    supplier_name, contact_person, phone, email,
                    gst_number, address, city, state, pincode,
                    payment_terms, opening_balance
                 ) VALUES (
                    :supplier_name, :contact_person, :phone, :email,
                    :gst_number, :address, :city, :state, :pincode,
                    :payment_terms, :opening_balance
                 )'
            );
            $stmt->execute([
                ':supplier_name' => $data['supplier_name'],
                ':contact_person' => $data['contact_person'],
                ':phone' => $data['phone'],
                ':email' => $data['email'],
                ':gst_number' => $data['gst_number'],
                ':address' => $data['address'],
                ':city' => $data['city'],
                ':state' => $data['state'],
                ':pincode' => $data['pincode'],
                ':payment_terms' => $data['payment_terms'],
                ':opening_balance' => $data['opening_balance'],
            ]);

            $_SESSION['flash_success'] = 'Supplier added successfully.';
            header('Location: ' . APP_BASE . '/suppliers/index.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = APP_DEBUG ? 'Database error: ' . $e->getMessage() : 'Failed to save supplier.';
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
                    <h2 class="mb-1 fw-bold">Add Supplier</h2>
                    <div class="avm-muted">Register a new procurement supplier.</div>
                </div>
                <a href="<?= APP_BASE ?>/suppliers/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
