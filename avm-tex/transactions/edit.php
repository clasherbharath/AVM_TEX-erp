<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/transaction_validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/transaction_schema.php';

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
$transactionSchema = getTransactionSchema($pdo);
$transactionPk = (string)$transactionSchema['primary_key'];

$invoiceStmt = $pdo->query(
    'SELECT i.id, i.invoice_number, c.customer_name
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id = c.id
     ORDER BY i.invoice_date DESC'
);
$invoiceOptions = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    'SELECT ' . implode(', ', getTransactionSelectParts($transactionSchema)) . '
     FROM transactions t
     WHERE t.' . $transactionPk . ' = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['flash_error'] = 'Transaction not found.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

$originalInvoiceId = isset($transaction['invoice_id']) && (int)$transaction['invoice_id'] > 0 ? (int)$transaction['invoice_id'] : null;

$form = transactionFormFromSource($transaction);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/transactions/edit.php?id=' . $id);

    $form = transactionFormFromSource($_POST);
    $errors = validateTransactionInput($form);

    if ($errors === []) {
        $data = normalizeTransactionInput($form);
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
                $data['amount'],
                $id
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
                'recorded_by' => $transaction['recorded_by'] ?? null,
            ]);

            $setParts = [];
            foreach ($write['columns'] as $column) {
                if ($column === 'recorded_by') {
                    continue;
                }

                $setParts[] = $column . ' = :' . $column;
            }

            // Build an execution params array that only contains keys used in the SET clause.
            $execParams = [];
            foreach ($write['columns'] as $column) {
                if ($column === 'recorded_by') {
                    continue;
                }
                $key = ':' . $column;
                $execParams[$key] = array_key_exists($key, $write['params']) ? $write['params'][$key] : null;
            }
            $execParams[':id'] = $id;

            $updateSql = 'UPDATE transactions SET ' . implode(', ', $setParts) . ' WHERE ' . $transactionPk . ' = :id';
            $update = $pdo->prepare($updateSql);
            $update->execute($execParams);

            if ($originalInvoiceId !== null) {
                syncInvoiceStatusFromTransactions($pdo, $originalInvoiceId, $id);
            }
            if ($data['invoice_id'] !== null && $data['invoice_id'] !== $originalInvoiceId) {
                syncInvoiceStatusFromTransactions($pdo, $data['invoice_id'], $id);
            }

            $pdo->commit();

            $_SESSION['flash_success'] = 'Transaction updated successfully.';
            header('Location: ' . APP_BASE . '/transactions/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? 'Database error: ' . $e->getMessage()
                : 'Failed to update the transaction. Please try again.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = APP_DEBUG
                ? $e->getMessage()
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
                    <div class="avm-muted">Recorded by <?= htmlspecialchars((string)($transaction['recorded_by'] ?? 'Unknown')) ?> on <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$transaction['created_at']))) ?>.</div>
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
