<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/transaction_schema.php';
require_once __DIR__ . '/../helpers/audit.php';

requireAdminRole('/transactions/index.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid transaction selected for deletion.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

requireValidCsrfToken('/transactions/index.php');

$transactionSchema = getTransactionSchema($pdo);
$transactionPk = (string)$transactionSchema['primary_key'];

$transactionStmt = $pdo->prepare(
    'SELECT invoice_id FROM transactions WHERE ' . $transactionPk . ' = :id LIMIT 1'
);
$transactionStmt->execute([':id' => $id]);
$transactionRow = $transactionStmt->fetch(PDO::FETCH_ASSOC);
$invoiceId = isset($transactionRow['invoice_id']) && (int)$transactionRow['invoice_id'] > 0 ? (int)$transactionRow['invoice_id'] : null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('DELETE FROM transactions WHERE ' . $transactionPk . ' = :id');
    $stmt->execute([':id' => $id]);

    if ($invoiceId !== null) {
        syncInvoiceStatusFromTransactions($pdo, $invoiceId);
    }

    $pdo->commit();

    // Audit transaction deletion
    logAudit($pdo, $_SESSION['admin_id'] ?? null, 'transaction_delete', 'transactions', $id, 'Transaction deleted');

    $_SESSION['flash_success'] = 'Transaction deleted successfully.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG
        ? 'Database error: ' . $e->getMessage()
        : 'Failed to delete the transaction. Please try again.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG
        ? $e->getMessage()
        : 'Failed to delete the transaction. Please try again.';
}

header('Location: ' . APP_BASE . '/transactions/index.php');
exit;
