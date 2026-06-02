<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Transactions • A.V.M TEX ERP';
$activeMenu = 'Transactions';

$search = trim((string)($_GET['q'] ?? ''));
$paymentMethod = trim((string)($_GET['payment_method'] ?? ''));
$transactions = [];
$totalAmount = 0.0;
$dbError = '';

$validMethods = [
    'cash' => 'Cash',
    'cheque' => 'Cheque',
    'bank_transfer' => 'Bank Transfer',
    'card' => 'Card',
    'other' => 'Other',
];

try {
    $sql = 'SELECT t.id, t.invoice_id, t.reference_number, t.transaction_date, t.transaction_type,
                   t.amount, t.payment_method, t.bank_name, t.cheque_number, t.transaction_notes,
                   t.recorded_by, t.created_at, t.updated_at,
                   i.invoice_number, c.customer_name
            FROM transactions t
            LEFT JOIN invoices i ON t.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE 1=1';
    $params = [];
    $totalParams = [];

    if ($search !== '') {
        $sql .= ' AND (t.id = :id_search OR t.transaction_type LIKE :search_type OR '
               . 't.payment_method LIKE :search_method OR t.transaction_notes LIKE :search_notes OR t.reference_number LIKE :search_reference '
               . 'OR i.invoice_number LIKE :search_invoice OR c.customer_name LIKE :search_customer)';
        $params[':id_search'] = is_numeric($search) ? (int)$search : 0;
        $params[':search_type'] = '%' . $search . '%';
        $params[':search_method'] = '%' . $search . '%';
        $params[':search_notes'] = '%' . $search . '%';
        $params[':search_reference'] = '%' . $search . '%';
        $params[':search_invoice'] = '%' . $search . '%';
        $params[':search_customer'] = '%' . $search . '%';

        $totalParams[':id_search'] = $params[':id_search'];
        $totalParams[':search_type'] = $params[':search_type'];
        $totalParams[':search_method'] = $params[':search_method'];
        $totalParams[':search_notes'] = $params[':search_notes'];
        $totalParams[':search_reference'] = $params[':search_reference'];
    }

    if ($paymentMethod !== '' && isset($validMethods[$paymentMethod])) {
        $sql .= ' AND t.payment_method = :payment_method';
        $params[':payment_method'] = $paymentMethod;
        $totalParams[':payment_method'] = $paymentMethod;
    }

    $sql .= ' ORDER BY t.transaction_date DESC, t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $totalSql = 'SELECT COALESCE(SUM(amount), 0) AS total_amount FROM transactions t WHERE 1=1';
    if ($search !== '') {
        $totalSql .= ' AND (t.id = :id_search OR t.transaction_type LIKE :search_type OR '
                   . 't.payment_method LIKE :search_method OR t.transaction_notes LIKE :search_notes OR t.reference_number LIKE :search_reference)';
    }
    if ($paymentMethod !== '' && isset($validMethods[$paymentMethod])) {
        $totalSql .= ' AND t.payment_method = :payment_method';
    }

    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($totalParams);
    $totalAmount = (float)($totalStmt->fetchColumn() ?? 0);
} catch (PDOException $e) {
    $dbError = APP_DEBUG
        ? $e->getMessage()
        : 'Could not load transactions. Please verify the transactions table exists.';
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
                    <h2 class="mb-1 fw-bold">Transactions</h2>
                    <div class="avm-muted">View and filter financial transaction history.</div>
                </div>
                <a href="<?= APP_BASE ?>/transactions/add.php" class="btn btn-avm-gold btn-sm">+ Add Transaction</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Transactions</div>
                            <div class="h4 mb-0 fw-bold"><?= number_format(count($transactions)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Total Amount</div>
                            <div class="h4 mb-0 fw-bold"><?= format_inr($totalAmount) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/transactions/index.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-6 col-lg-5">
                            <label for="searchInput" class="form-label small fw-semibold">Search</label>
                            <input type="search" id="searchInput" name="q" class="form-control"
                                   placeholder="Invoice #, customer, type, method, notes"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-4 col-lg-3">
                            <label for="paymentMethod" class="form-label small fw-semibold">Payment Method</label>
                            <select id="paymentMethod" name="payment_method" class="form-select">
                                <option value="">All Methods</option>
                                <?php foreach ($validMethods as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= $paymentMethod === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 col-lg-2">
                            <button type="submit" class="btn btn-avm-gold w-100">Filter</button>
                        </div>
                        <div class="col-12 col-md-12 col-lg-2">
                            <a href="<?= APP_BASE ?>/transactions/index.php" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Notes</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) === 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        No transactions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= (int)$transaction['id'] ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($transaction['transaction_date'] ?? $transaction['created_at']))) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($transaction['transaction_type'])) ?></td>
                                        <td><?= htmlspecialchars($validMethods[$transaction['payment_method']] ?? $transaction['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($transaction['invoice_number'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($transaction['customer_name'] ?? '—') ?></td>
                                        <td class="fw-semibold"><?= format_inr((float)$transaction['amount']) ?></td>
                                        <td><?= htmlspecialchars($transaction['transaction_notes'] ?? '') ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?= APP_BASE ?>/transactions/view.php?id=<?= (int)$transaction['id'] ?>" class="btn btn-outline-dark">View</a>
                                                <a href="<?= APP_BASE ?>/transactions/edit.php?id=<?= (int)$transaction['id'] ?>" class="btn btn-outline-secondary">Edit</a>
                                                <form method="post" action="<?= APP_BASE ?>/transactions/delete.php" class="d-inline" onsubmit="return confirm('Delete this transaction?');">
                                                    <input type="hidden" name="id" value="<?= (int)$transaction['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
