<?php
/**
 * Customer form validation helpers.
 * Column names match MySQL table: customers
 */
declare(strict_types=1);

/**
 * @return array<string, string> Field => error message
 */
function validateCustomerInput(array $data): array
{
    $errors = [];

    $name = trim((string)($data['customer_name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $state = trim((string)($data['state'] ?? ''));
    $pincode = trim((string)($data['pincode'] ?? ''));
    $gst = strtoupper(trim((string)($data['gst_number'] ?? '')));

    if ($name === '') {
        $errors['customer_name'] = 'Customer name is required.';
    } elseif (mb_strlen($name) < 2) {
        $errors['customer_name'] = 'Customer name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 150) {
        $errors['customer_name'] = 'Customer name cannot exceed 150 characters.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $errors['phone'] = 'Enter a valid 10-digit phone number.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($address === '') {
        $errors['address'] = 'Address is required.';
    } elseif (mb_strlen($address) < 5) {
        $errors['address'] = 'Address must be at least 5 characters.';
    }

    if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
        $errors['pincode'] = 'Pincode must be 6 digits.';
    }

    if ($gst !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst)) {
        $errors['gst_number'] = 'Enter a valid 15-character GST number.';
    }

    return $errors;
}

/**
 * @return array{
 *   customer_name: string,
 *   phone: string,
 *   gst_number: ?string,
 *   email: ?string,
 *   address: string,
 *   city: ?string,
 *   state: ?string,
 *   pincode: ?string
 * }
 */
function normalizeCustomerInput(array $data): array
{
    $gst = strtoupper(trim((string)($data['gst_number'] ?? '')));
    $email = trim((string)($data['email'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $state = trim((string)($data['state'] ?? ''));
    $pincode = trim((string)($data['pincode'] ?? ''));

    return [
        'customer_name' => trim((string)($data['customer_name'] ?? '')),
        'phone' => trim((string)($data['phone'] ?? '')),
        'gst_number' => $gst !== '' ? $gst : null,
        'email' => $email !== '' ? $email : null,
        'address' => trim((string)($data['address'] ?? '')),
        'city' => $city !== '' ? $city : null,
        'state' => $state !== '' ? $state : null,
        'pincode' => $pincode !== '' ? $pincode : null,
    ];
}

/**
 * Default empty form aligned with database columns.
 *
 * @return array<string, string>
 */
function emptyCustomerForm(): array
{
    return [
        'customer_name' => '',
        'phone' => '',
        'gst_number' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
    ];
}

/**
 * Build form array from POST or database row.
 *
 * @param array<string, mixed> $source
 * @return array<string, string>
 */
function customerFormFromSource(array $source): array
{
    return [
        'customer_name' => (string)($source['customer_name'] ?? ''),
        'phone' => (string)($source['phone'] ?? ''),
        'gst_number' => (string)($source['gst_number'] ?? ''),
        'email' => (string)($source['email'] ?? ''),
        'address' => (string)($source['address'] ?? ''),
        'city' => (string)($source['city'] ?? ''),
        'state' => (string)($source['state'] ?? ''),
        'pincode' => (string)($source['pincode'] ?? ''),
    ];
}
