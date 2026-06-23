<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/transaction_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/transaction_schema.php';
require_once __DIR__ . '/../helpers/audit.php';

$pageTitle = 'Add Transaction • A.V.M TEX ERP';
$activeMenu = 'Transactions';

$errors = [];
$form = emptyTransactionForm();
$invoiceOptions = [];
$transactionSchema = getTransactionSchema($pdo);

$stmt = $pdo->query(
    'SELECT i.id, i.invoice_number, c.customer_name
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id = c.id
     ORDER BY i.invoice_date DESC'
);
$invoiceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/transactions/add.php');

    $form = transactionFormFromSource($_POST);
    $errors = validateTransactionInput($form);

    if ($errors === []) {
        $data = normalizeTransactionInput($form);
        $recordedBy = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        $customerId = getInvoiceCustomerId($pdo, $data['invoice_id']);

        if ($data['invoice_id'] !== null) {
            $invoiceCheck = $pdo->prepare('SELECT id FROM invoices WHERE id = :id LIMIT 1');
            $invoiceCheck->execute([':id' => $data['invoice_id']]);
            if (!$invoiceCheck->fetch()) {
                $errors['invoice_id'] = 'Select a valid invoice.';
            }
        }

        if ($errors === [] && !empty($transactionSchema['customer_id_required']) && $customerId === null) {
            $errors['invoice_id'] = 'Select an invoice before saving this transaction.';
        }

        if ($errors === [] && $data['invoice_id'] !== null) {
            $amountError = validateInvoiceTransactionAmount(
                $pdo,
                $data['invoice_id'],
                $data['transaction_type'],
                $data['amount']
            );

            if ($amountError !== null) {
                $errors['amount'] = $amountError;
            }
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $write = buildTransactionWriteSet($transactionSchema, [
                'transaction_type' => $data['transaction_type'],
                'invoice_id' => $data['invoice_id'],
                'customer_id' => $customerId,
                'reference_number' => $data['reference_number'],
                'transaction_date' => $data['transaction_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'bank_name' => $data['bank_name'],
                'cheque_number' => $data['cheque_number'],
                'transaction_notes' => $data['transaction_notes'],
                'recorded_by' => $recordedBy,
            ]);

            $placeholders = [];
            foreach ($write['columns'] as $column) {
                $placeholders[] = ':' . $column;
            }

            $insert = $pdo->prepare(
                'INSERT INTO transactions (' . implode(', ', $write['columns']) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $insert->execute($write['params']);

            $transactionId = (int)$pdo->lastInsertId();

            if ($data['invoice_id'] !== null) {
                syncInvoiceStatusFromTransactions($pdo, $data['invoice_id']);
            }

            $pdo->commit();

            // Audit transaction creation
            logAudit($pdo, $_SESSION['admin_id'] ?? null, 'transaction_create', 'transactions', $transactionId, 'Transaction created');

            $_SESSION['flash_success'] = 'Transaction added successfully.';
            header('Location: ' . APP_BASE . '/transactions/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to save the transaction. Please try again.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? $e->getMessage()
                : 'Failed to save the transaction. Please try again.';
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
                    <h2 class="mb-1 fw-bold">Add Transaction</h2>
                    <div class="avm-muted">Record a new financial transaction.</div>
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
                    $formAction = APP_BASE . '/transactions/add.php';
                    $submitLabel = 'Save Transaction';
                    require __DIR__ . '/_form.php';
                    ?>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
