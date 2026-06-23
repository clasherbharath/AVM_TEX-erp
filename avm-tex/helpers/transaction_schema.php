<?php
declare(strict_types=1);

/**
 * Transaction table compatibility helpers.
 *
 * The project ships with more than one transaction schema shape across SQL
 * files and runtime code. These helpers keep reads and writes backward
 * compatible with the actual table currently installed.
 */

/**
 * @return array<string, array<string, mixed>>
 */
function getTableSchema(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $schema = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $field = (string)($row['Field'] ?? '');
        if ($field === '') {
            continue;
        }

        $schema[$field] = $row;
    }

    $cache[$table] = $schema;

    return $schema;
}

/**
 * @return array{
 *   columns: array<string, array<string, mixed>>,
 *   primary_key: string,
 *   date_column: string,
 *   notes_column: string,
 *   reference_column: string|null,
 *   bank_name_column: string|null,
 *   cheque_number_column: string|null,
 *   recorded_by_column: string|null,
 *   updated_at_column: string|null,
 *   customer_id_column: string|null,
 *   customer_id_required: bool
 * }
 */
function getTransactionSchema(PDO $pdo): array
{
    $columns = getTableSchema($pdo, 'transactions');
    $customerIdColumn = isset($columns['customer_id']) ? 'customer_id' : null;

    return [
        'columns' => $columns,
        'primary_key' => isset($columns['id']) ? 'id' : 'transaction_id',
        'date_column' => isset($columns['transaction_date']) ? 'transaction_date' : 'created_at',
        'notes_column' => isset($columns['transaction_notes']) ? 'transaction_notes' : 'notes',
        'reference_column' => isset($columns['reference_number']) ? 'reference_number' : null,
        'bank_name_column' => isset($columns['bank_name']) ? 'bank_name' : null,
        'cheque_number_column' => isset($columns['cheque_number']) ? 'cheque_number' : null,
        'recorded_by_column' => isset($columns['recorded_by']) ? 'recorded_by' : null,
        'updated_at_column' => isset($columns['updated_at']) ? 'updated_at' : null,
        'customer_id_column' => $customerIdColumn,
        'customer_id_required' => $customerIdColumn !== null
            && (($columns[$customerIdColumn]['Null'] ?? 'YES') === 'NO'),
    ];
}

/**
 * @param array<string, mixed> $schema
 */
function transactionColumnExists(array $schema, string $column): bool
{
    return isset($schema['columns'][$column]);
}

/**
 * @param array<string, mixed> $schema
 * @return array<int, string>
 */
function getTransactionSelectParts(array $schema): array
{
    $pk = (string)$schema['primary_key'];
    $dateColumn = (string)$schema['date_column'];
    $notesColumn = (string)$schema['notes_column'];

    return [
        "t.{$pk} AS transaction_id",
        't.invoice_id',
        transactionColumnExists($schema, 'customer_id') ? 't.customer_id' : 'NULL AS customer_id',
        't.transaction_type',
        "t.{$dateColumn} AS transaction_date",
        't.amount',
        't.payment_method',
        transactionColumnExists($schema, 'reference_number') ? 't.reference_number' : 'NULL AS reference_number',
        transactionColumnExists($schema, 'bank_name') ? 't.bank_name' : 'NULL AS bank_name',
        transactionColumnExists($schema, 'cheque_number') ? 't.cheque_number' : 'NULL AS cheque_number',
        "t.{$notesColumn} AS transaction_notes",
        transactionColumnExists($schema, 'recorded_by') ? 't.recorded_by' : 'NULL AS recorded_by',
        't.created_at',
        transactionColumnExists($schema, 'updated_at') ? 't.updated_at' : 'NULL AS updated_at',
    ];
}

/**
 * @param array<string, mixed> $schema
 */
function getTransactionCustomerJoin(array $schema): string
{
    if (transactionColumnExists($schema, 'customer_id')) {
        return 'LEFT JOIN customers c ON c.id = COALESCE(t.customer_id, i.customer_id)';
    }

    return 'LEFT JOIN customers c ON c.id = i.customer_id';
}

/**
 * @param array<string, mixed> $schema
 * @return array{columns: array<int, string>, params: array<string, mixed>}
 */
function buildTransactionWriteSet(array $schema, array $data): array
{
    $columns = ['transaction_type', 'invoice_id', 'amount', 'payment_method'];
    $params = [
        ':transaction_type' => $data['transaction_type'],
        ':invoice_id' => $data['invoice_id'],
        ':amount' => $data['amount'],
        ':payment_method' => $data['payment_method'],
    ];

    if (transactionColumnExists($schema, 'customer_id')) {
        $columns[] = 'customer_id';
        $params[':customer_id'] = $data['customer_id'];
    }

    if (transactionColumnExists($schema, 'reference_number')) {
        $columns[] = 'reference_number';
        $params[':reference_number'] = $data['reference_number'];
    }

    if (transactionColumnExists($schema, 'transaction_date')) {
        $columns[] = 'transaction_date';
        $params[':transaction_date'] = $data['transaction_date'];
    }

    if (transactionColumnExists($schema, 'bank_name')) {
        $columns[] = 'bank_name';
        $params[':bank_name'] = $data['bank_name'];
    }

    if (transactionColumnExists($schema, 'cheque_number')) {
        $columns[] = 'cheque_number';
        $params[':cheque_number'] = $data['cheque_number'];
    }

    $notesColumn = (string)$schema['notes_column'];
    $columns[] = $notesColumn;
    $params[':' . $notesColumn] = $data['transaction_notes'];

    if (transactionColumnExists($schema, 'recorded_by')) {
        $columns[] = 'recorded_by';
        $params[':recorded_by'] = $data['recorded_by'];
    }

    return [
        'columns' => $columns,
        'params' => $params,
    ];
}

/**
 * @param array<string, mixed> $schema
 * @return array<int, string>
 */
function getTransactionSearchConditions(array $schema): array
{
    $pk = (string)$schema['primary_key'];
    $conditions = [
        "t.{$pk} = :id_search",
        't.transaction_type LIKE :search_type',
        't.payment_method LIKE :search_method',
        'c.customer_name LIKE :search_customer',
        'i.invoice_number LIKE :search_invoice',
    ];

    if (transactionColumnExists($schema, 'reference_number')) {
        $conditions[] = 't.reference_number LIKE :search_reference';
    }

    $notesColumn = (string)$schema['notes_column'];
    $conditions[] = "t.{$notesColumn} LIKE :search_notes";

    return $conditions;
}

/**
 * @param array<string, mixed> $schema
 */
function getTransactionOrderBy(array $schema): string
{
    if (transactionColumnExists($schema, 'transaction_date')) {
        return 't.transaction_date DESC, t.created_at DESC';
    }

    return 't.created_at DESC';
}

function getInvoiceCustomerId(PDO $pdo, ?int $invoiceId): ?int
{
    if ($invoiceId === null || $invoiceId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT customer_id FROM invoices WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $invoiceId]);
    $customerId = $stmt->fetchColumn();

    return $customerId !== false ? (int)$customerId : null;
}

/**
 * Fetch invoice totals and transaction settlement data.
 *
 * @return array{
 *   invoice_id: int,
 *   invoice_number: string,
 *   invoice_status: string,
 *   grand_total: float,
 *   total_payments: float,
 *   total_refunds: float,
 *   total_credit_memos: float,
 *   total_adjustments: float,
 *   net_applied: float,
 *   balance_due: float
 * }|null
 */
function getInvoiceTransactionSummary(PDO $pdo, int $invoiceId, ?int $excludeTransactionId = null): ?array
{
    if ($invoiceId <= 0) {
        return null;
    }

    $transactionSchema = getTransactionSchema($pdo);
    $transactionPk = (string)$transactionSchema['primary_key'];

    $excludeJoin = '';
    $params = [':invoice_id' => $invoiceId];
    if ($excludeTransactionId !== null && $excludeTransactionId > 0) {
        $excludeJoin = ' AND t.' . $transactionPk . ' <> :exclude_transaction_id';
        $params[':exclude_transaction_id'] = $excludeTransactionId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            i.id AS invoice_id,
            i.invoice_number,
            i.status AS invoice_status,
            i.grand_total,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) AS total_payments,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'refund' THEN t.amount ELSE 0 END), 0) AS total_refunds,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'credit_memo' THEN t.amount ELSE 0 END), 0) AS total_credit_memos,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'adjustment' THEN t.amount ELSE 0 END), 0) AS total_adjustments,
            COALESCE(SUM(
                CASE
                    WHEN t.transaction_type = 'payment' THEN t.amount
                    WHEN t.transaction_type IN ('refund', 'credit_memo') THEN -t.amount
                    ELSE 0
                END
            ), 0) AS net_applied
         FROM invoices i
         LEFT JOIN transactions t ON t.invoice_id = i.id" . $excludeJoin . "
         WHERE i.id = :invoice_id
         GROUP BY i.id
         LIMIT 1"
    );
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($summary)) {
        return null;
    }

    $grandTotal = (float)($summary['grand_total'] ?? 0);
    $netApplied = (float)($summary['net_applied'] ?? 0);
    $balanceDue = max(0, round($grandTotal - $netApplied, 2));

    return [
        'invoice_id' => (int)$summary['invoice_id'],
        'invoice_number' => (string)$summary['invoice_number'],
        'invoice_status' => (string)$summary['invoice_status'],
        'grand_total' => round($grandTotal, 2),
        'total_payments' => round((float)($summary['total_payments'] ?? 0), 2),
        'total_refunds' => round((float)($summary['total_refunds'] ?? 0), 2),
        'total_credit_memos' => round((float)($summary['total_credit_memos'] ?? 0), 2),
        'total_adjustments' => round((float)($summary['total_adjustments'] ?? 0), 2),
        'net_applied' => round($netApplied, 2),
        'balance_due' => round($balanceDue, 2),
    ];
}

/**
 * Returns null when the transaction amount is acceptable, otherwise an error message.
 */
function validateInvoiceTransactionAmount(PDO $pdo, int $invoiceId, string $transactionType, float $amount, ?int $excludeTransactionId = null): ?string
{
    $summary = getInvoiceTransactionSummary($pdo, $invoiceId, $excludeTransactionId);
    if ($summary === null) {
        return 'Select a valid invoice.';
    }

    if ($summary['invoice_status'] === 'cancelled') {
        return 'Cancelled invoices cannot receive transactions.';
    }

    $amount = round($amount, 2);
    $balanceDue = $summary['balance_due'];
    $collected = max(0, round($summary['net_applied'], 2));

    if ($transactionType === 'payment' && $amount > $balanceDue + 0.01) {
        return 'Payment amount exceeds the remaining balance of ' . number_format($balanceDue, 2) . '.';
    }

    if (in_array($transactionType, ['refund', 'credit_memo'], true) && $amount > $collected + 0.01) {
        return ucfirst(str_replace('_', ' ', $transactionType)) . ' amount exceeds the collected amount of ' . number_format($collected, 2) . '.';
    }

    return null;
}

/**
 * Sync invoice status to the current transaction balance.
 */
function syncInvoiceStatusFromTransactions(PDO $pdo, int $invoiceId, ?int $excludeTransactionId = null): void
{
    $summary = getInvoiceTransactionSummary($pdo, $invoiceId, $excludeTransactionId);
    if ($summary === null || $summary['invoice_status'] === 'cancelled') {
        return;
    }

    $newStatus = $summary['balance_due'] <= 0.01 ? 'paid' : 'pending';
    if ($newStatus === $summary['invoice_status']) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE invoices SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $invoiceId,
    ]);
}
