<?php
/**
 * Transaction form validation helpers.
 * Column names match MySQL table: transactions
 */
declare(strict_types=1);

/**
 * @return array<string, string>
 */
function validateTransactionInput(array $data): array
{
    $errors = [];

    $transactionType = trim((string)($data['transaction_type'] ?? ''));
    $invoiceId = trim((string)($data['invoice_id'] ?? ''));
    $referenceNumber = trim((string)($data['reference_number'] ?? ''));
    $transactionDate = trim((string)($data['transaction_date'] ?? ''));
    $amount = trim((string)($data['amount'] ?? ''));
    $paymentMethod = trim((string)($data['payment_method'] ?? ''));
    $bankName = trim((string)($data['bank_name'] ?? ''));
    $chequeNumber = trim((string)($data['cheque_number'] ?? ''));
    $notes = trim((string)($data['transaction_notes'] ?? ''));

    $validTypes = ['payment', 'refund', 'adjustment', 'credit_memo'];
    $validMethods = ['cash', 'cheque', 'bank_transfer', 'card', 'other'];

    if ($transactionType === '' || !in_array($transactionType, $validTypes, true)) {
        $errors['transaction_type'] = 'Please select a valid transaction type.';
    }

    if ($transactionDate === '') {
        $errors['transaction_date'] = 'Transaction date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate) || strtotime($transactionDate) === false) {
        $errors['transaction_date'] = 'Enter a valid transaction date.';
    }

    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $errors['amount'] = 'Enter a valid transaction amount greater than 0.';
    }

    if ($paymentMethod === '' || !in_array($paymentMethod, $validMethods, true)) {
        $errors['payment_method'] = 'Please select a payment method.';
    }

    if ($paymentMethod === 'bank_transfer' && $bankName === '') {
        $errors['bank_name'] = 'Bank name is required for bank transfer payments.';
    }

    if ($paymentMethod === 'cheque' && $chequeNumber === '') {
        $errors['cheque_number'] = 'Cheque number is required for cheque payments.';
    }

    if ($invoiceId !== '' && !ctype_digit($invoiceId)) {
        $errors['invoice_id'] = 'Select a valid invoice.';
    }

    if ($referenceNumber !== '' && mb_strlen($referenceNumber) > 100) {
        $errors['reference_number'] = 'Reference number cannot exceed 100 characters.';
    }

    if (mb_strlen($notes) > 500) {
        $errors['transaction_notes'] = 'Notes cannot exceed 500 characters.';
    }

    return $errors;
}

/**
 * @return array<string, mixed>
 */
function normalizeTransactionInput(array $data): array
{
    $invoiceId = trim((string)($data['invoice_id'] ?? ''));
    $referenceNumber = trim((string)($data['reference_number'] ?? ''));
    $transactionDate = trim((string)($data['transaction_date'] ?? ''));
    $amount = trim((string)($data['amount'] ?? ''));
    $paymentMethod = trim((string)($data['payment_method'] ?? ''));
    $bankName = trim((string)($data['bank_name'] ?? ''));
    $chequeNumber = trim((string)($data['cheque_number'] ?? ''));
    $notes = trim((string)($data['transaction_notes'] ?? ''));

    return [
        'transaction_type' => trim((string)($data['transaction_type'] ?? '')),
        'invoice_id' => $invoiceId !== '' ? (int)$invoiceId : null,
        'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
        'transaction_date' => $transactionDate,
        'amount' => (float)$amount,
        'payment_method' => $paymentMethod,
        'bank_name' => $bankName !== '' ? $bankName : null,
        'cheque_number' => $chequeNumber !== '' ? $chequeNumber : null,
        'transaction_notes' => $notes !== '' ? $notes : null,
    ];
}

/**
 * @return array<string, mixed>
 */
function emptyTransactionForm(): array
{
    return [
        'transaction_type' => 'payment',
        'invoice_id' => '',
        'reference_number' => '',
        'transaction_date' => date('Y-m-d'),
        'amount' => '',
        'payment_method' => 'cash',
        'bank_name' => '',
        'cheque_number' => '',
        'transaction_notes' => '',
    ];
}

/**
 * @param array<string, mixed> $source
 * @return array<string, mixed>
 */
function transactionFormFromSource(array $source): array
{
    return [
        'transaction_type' => (string)($source['transaction_type'] ?? 'payment'),
        'invoice_id' => (string)($source['invoice_id'] ?? ''),
        'reference_number' => (string)($source['reference_number'] ?? ''),
        'transaction_date' => (string)($source['transaction_date'] ?? ''),
        'amount' => isset($source['amount']) ? (string)$source['amount'] : '',
        'payment_method' => (string)($source['payment_method'] ?? 'cash'),
        'bank_name' => (string)($source['bank_name'] ?? ''),
        'cheque_number' => (string)($source['cheque_number'] ?? ''),
        'transaction_notes' => (string)($source['transaction_notes'] ?? ''),
    ];
}
