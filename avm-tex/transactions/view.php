<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'View Transaction • A.V.M TEX ERP';
$activeMenu = 'Transactions';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid transaction selected.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT t.id, t.transaction_type, t.invoice_id, t.reference_number,
            t.transaction_date, t.amount, t.payment_method, t.bank_name,
            t.cheque_number, t.transaction_notes, t.recorded_by, t.created_at, t.updated_at,
            i.invoice_number, c.customer_name
     FROM transactions t
     LEFT JOIN invoices i ON t.invoice_id = i.id
     LEFT JOIN customers c ON i.customer_id = c.id
     WHERE t.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['flash_error'] = 'Transaction not found.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
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
                    <h2 class="mb-1 fw-bold">Transaction Details</h2>
                    <div class="avm-muted">Review transaction information and history.</div>
                </div>
                <a href="<?= APP_BASE ?>/transactions/index.php" class="btn btn-outline-secondary btn-sm">← Back to list</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Transaction ID</div>
                            <div class="fw-semibold"><?= (int)$transaction['id'] ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Recorded By</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['recorded_by'] ?? '—') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Transaction Type</div>
                            <div class="fw-semibold"><?= htmlspecialchars(ucfirst($transaction['transaction_type'])) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Payment Method</div>
                            <div class="fw-semibold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $transaction['payment_method']))) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Invoice</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['invoice_number'] ?? '—') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Customer</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['customer_name'] ?? '—') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Reference #</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['reference_number'] ?? '—') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Transaction Date</div>
                            <div class="fw-semibold"><?= htmlspecialchars(date('d M Y', strtotime((string)$transaction['transaction_date']))) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Amount</div>
                            <div class="fw-semibold text-success"><?= format_inr((float)$transaction['amount']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Bank Name</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['bank_name'] ?? '—') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Cheque Number</div>
                            <div class="fw-semibold"><?= htmlspecialchars($transaction['cheque_number'] ?? '—') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Notes</div>
                            <div class="fw-semibold"><?= nl2br(htmlspecialchars($transaction['transaction_notes'] ?? '—')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Created At</div>
                            <div class="fw-semibold"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$transaction['created_at']))) ?></div>
                        </div>
                        <?php if (!empty($transaction['updated_at'])): ?>
                            <div class="col-md-6">
                                <div class="small text-muted">Updated At</div>
                                <div class="fw-semibold"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$transaction['updated_at']))) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
