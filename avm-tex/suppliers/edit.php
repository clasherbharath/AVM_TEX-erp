<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/supplier_validation.php';
require_once __DIR__ . '/../includes/security.php';

$pageTitle = 'Edit Supplier • A.V.M TEX ERP';
$activeMenu = 'Suppliers';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier selected.';
    header('Location: ' . APP_BASE . '/suppliers/index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, supplier_name, contact_person, phone, email, gst_number, address, city, state, pincode, payment_terms, opening_balance, created_at
     FROM suppliers WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    $_SESSION['flash_error'] = 'Supplier not found.';
    header('Location: ' . APP_BASE . '/suppliers/index.php');
    exit;
}

$form = supplierFormFromSource($supplier);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/suppliers/edit.php?id=' . $id);

    $form = supplierFormFromSource($_POST);
    $errors = validateSupplierInput($form);

    if ($errors === []) {
        $data = normalizeSupplierInput($form);

        try {
            $update = $pdo->prepare(
                'UPDATE suppliers SET
                    supplier_name = :supplier_name,
                    contact_person = :contact_person,
                    phone = :phone,
                    email = :email,
                    gst_number = :gst_number,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    payment_terms = :payment_terms,
                    opening_balance = :opening_balance
                 WHERE id = :id'
            );
            $update->execute([
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
                ':id' => $id,
            ]);

            $_SESSION['flash_success'] = 'Supplier updated successfully.';
            header('Location: ' . APP_BASE . '/suppliers/index.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = APP_DEBUG ? 'Database error: ' . $e->getMessage() : 'Failed to update supplier.';
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
                    <h2 class="mb-1 fw-bold">Edit Supplier</h2>
                    <div class="avm-muted">Created on <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$supplier['created_at']))) ?></div>
                </div>
                <a href="<?= APP_BASE ?>/suppliers/index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
            <?php endif; ?>

            <div class="card avm-card">
                <div class="card-body">
                    <?php
                    $formAction = APP_BASE . '/suppliers/edit.php';
                    $submitLabel = 'Update Supplier';
                    $hiddenId = $id;
                    require __DIR__ . '/_form.php';
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
