<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/transaction_validation.php';

$pageTitle = 'Edit Transaction • A.V.M TEX ERP';
$activeMenu = 'Transactions';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid transaction selected.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

$errors = [];
$invoiceOptions = [];

$invoiceStmt = $pdo->query(
    'SELECT i.id, i.invoice_number, c.customer_name
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id = c.id
     ORDER BY i.invoice_date DESC'
);
$invoiceOptions = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    'SELECT t.id, t.transaction_type, t.invoice_id, t.reference_number,
            t.transaction_date, t.amount, t.payment_method, t.bank_name,
            t.cheque_number, t.transaction_notes, t.recorded_by, t.created_at
     FROM transactions t
     WHERE t.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['flash_error'] = 'Transaction not found.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

$form = transactionFormFromSource($transaction);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = transactionFormFromSource($_POST);
    $errors = validateTransactionInput($form);

    if ($errors === []) {
        $data = normalizeTransactionInput($form);

        if ($data['invoice_id'] !== null) {
            $invoiceCheck = $pdo->prepare('SELECT id FROM invoices WHERE id = :id LIMIT 1');
            $invoiceCheck->execute([':id' => $data['invoice_id']]);
            if (!$invoiceCheck->fetch()) {
                $errors['invoice_id'] = 'Select a valid invoice.';
            }
        }
    }

    if ($errors === []) {
        try {
            $update = $pdo->prepare(
                'UPDATE transactions SET
                    transaction_type = :transaction_type,
                    invoice_id = :invoice_id,
                    reference_number = :reference_number,
                    transaction_date = :transaction_date,
                    amount = :amount,
                    payment_method = :payment_method,
                    bank_name = :bank_name,
                    cheque_number = :cheque_number,
                    transaction_notes = :transaction_notes
                 WHERE id = :id'
            );
            $update->execute([
                ':transaction_type' => $data['transaction_type'],
                ':invoice_id' => $data['invoice_id'],
                ':reference_number' => $data['reference_number'],
                ':transaction_date' => $data['transaction_date'],
                ':amount' => $data['amount'],
                ':payment_method' => $data['payment_method'],
                ':bank_name' => $data['bank_name'],
                ':cheque_number' => $data['cheque_number'],
                ':transaction_notes' => $data['transaction_notes'],
                ':id' => $id,
            ]);

            $_SESSION['flash_success'] = 'Transaction updated successfully.';
            header('Location: ' . APP_BASE . '/transactions/index.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to update the transaction. Please try again.';
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
                    <h2 class="mb-1 fw-bold">Edit Transaction</h2>
                    <div class="avm-muted">Recorded by <?= htmlspecialchars($transaction['recorded_by'] ?? 'Unknown') ?> on <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$transaction['created_at']))) ?>.</div>
                </div>
                <a href="<?= APP_BASE ?>/transactions/index.php" class="btn btn-outline-secondary btn-sm">← Back to list</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
            <?php endif; ?>

            <div class="card avm-card">
                <div class="card-body">
                    <?php
                    $formAction = APP_BASE . '/transactions/edit.php';
                    $submitLabel = 'Update Transaction';
                    $transactionId = $id;
                    require __DIR__ . '/_form.php';
                    ?>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
