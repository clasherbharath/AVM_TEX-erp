<?php
/**
 * Supplier form validation helpers.
 */
declare(strict_types=1);

/**
 * @return array<string, string>
 */
function validateSupplierInput(array $data): array
{
    $errors = [];

    $supplierName = trim((string)($data['supplier_name'] ?? ''));
    $contactPerson = trim((string)($data['contact_person'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $gst = strtoupper(trim((string)($data['gst_number'] ?? '')));
    $address = trim((string)($data['address'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $state = trim((string)($data['state'] ?? ''));
    $pincode = trim((string)($data['pincode'] ?? ''));
    $paymentTerms = trim((string)($data['payment_terms'] ?? ''));
    $openingBalance = trim((string)($data['opening_balance'] ?? '0'));

    if ($supplierName === '') {
        $errors['supplier_name'] = 'Supplier name is required.';
    } elseif (mb_strlen($supplierName) > 150) {
        $errors['supplier_name'] = 'Supplier name cannot exceed 150 characters.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[6-9]\d{9}$/', preg_replace('/\D+/', '', $phone))) {
        $errors['phone'] = 'Enter a valid 10-digit phone number.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($gst !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst)) {
        $errors['gst_number'] = 'Enter a valid 15-character GST number.';
    }

    if ($address === '') {
        $errors['address'] = 'Address is required.';
    }

    if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
        $errors['pincode'] = 'Pincode must be 6 digits.';
    }

    if ($openingBalance === '' || !is_numeric($openingBalance)) {
        $errors['opening_balance'] = 'Opening balance must be numeric.';
    }

    if ($paymentTerms !== '' && mb_strlen($paymentTerms) > 100) {
        $errors['payment_terms'] = 'Payment terms cannot exceed 100 characters.';
    }

    if ($contactPerson !== '' && mb_strlen($contactPerson) > 150) {
        $errors['contact_person'] = 'Contact person cannot exceed 150 characters.';
    }

    return $errors;
}

/**
 * @return array<string, mixed>
 */
function normalizeSupplierInput(array $data): array
{
    $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
    $gst = strtoupper(trim((string)($data['gst_number'] ?? '')));
    $email = trim((string)($data['email'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $state = trim((string)($data['state'] ?? ''));
    $pincode = trim((string)($data['pincode'] ?? ''));
    $paymentTerms = trim((string)($data['payment_terms'] ?? ''));

    return [
        'supplier_name' => trim((string)($data['supplier_name'] ?? '')),
        'contact_person' => trim((string)($data['contact_person'] ?? '')) !== '' ? trim((string)$data['contact_person']) : null,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'gst_number' => $gst !== '' ? $gst : null,
        'address' => trim((string)($data['address'] ?? '')),
        'city' => $city !== '' ? $city : null,
        'state' => $state !== '' ? $state : null,
        'pincode' => $pincode !== '' ? $pincode : null,
        'payment_terms' => $paymentTerms !== '' ? $paymentTerms : null,
        'opening_balance' => (float)($data['opening_balance'] ?? 0),
    ];
}

/**
 * @return array<string, string>
 */
function emptySupplierForm(): array
{
    return [
        'supplier_name' => '',
        'contact_person' => '',
        'phone' => '',
        'email' => '',
        'gst_number' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
        'payment_terms' => '',
        'opening_balance' => '0',
    ];
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string>
 */
function supplierFormFromSource(array $source): array
{
    return [
        'supplier_name' => (string)($source['supplier_name'] ?? ''),
        'contact_person' => (string)($source['contact_person'] ?? ''),
        'phone' => (string)($source['phone'] ?? ''),
        'email' => (string)($source['email'] ?? ''),
        'gst_number' => (string)($source['gst_number'] ?? ''),
        'address' => (string)($source['address'] ?? ''),
        'city' => (string)($source['city'] ?? ''),
        'state' => (string)($source['state'] ?? ''),
        'pincode' => (string)($source['pincode'] ?? ''),
        'payment_terms' => (string)($source['payment_terms'] ?? ''),
        'opening_balance' => isset($source['opening_balance']) ? (string)$source['opening_balance'] : '0',
    ];
}
