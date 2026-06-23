<?php
/**
 * Procurement / purchase order validation helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../helpers/procurement.php';

/**
 * @return array<string, string>
 */
function validatePurchaseOrderInput(PDO $pdo, array $post): array
{
    $errors = [];
    $supplierId = (int)($post['supplier_id'] ?? 0);
    $orderDate = trim((string)($post['order_date'] ?? ''));
    $expectedDate = trim((string)($post['expected_date'] ?? ''));
    $status = (string)($post['status'] ?? 'ordered');
    $discount = (float)($post['discount'] ?? 0);

    if ($supplierId <= 0) {
        $errors['supplier_id'] = 'Please select a supplier.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM suppliers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $supplierId]);
        if (!$stmt->fetch()) {
            $errors['supplier_id'] = 'Selected supplier does not exist.';
        }
    }

    if ($orderDate === '' || strtotime($orderDate) === false) {
        $errors['order_date'] = 'Valid order date is required.';
    }

    if ($expectedDate !== '' && strtotime($expectedDate) === false) {
        $errors['expected_date'] = 'Enter a valid expected date.';
    }

    if (!in_array($status, ['draft', 'ordered', 'partial', 'received', 'cancelled'], true)) {
        $errors['status'] = 'Invalid purchase status.';
    }

    if ($discount < 0) {
        $errors['discount'] = 'Discount cannot be negative.';
    }

    $rows = extractPurchaseRows($post);

    if ($rows === []) {
        $errors['items'] = 'Add at least one purchase item.';
    } else {
        foreach ($rows as $idx => $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $qty = (float)($row['quantity'] ?? 0);
            $price = (float)($row['purchase_price'] ?? 0);
            $gst = (float)($row['gst_percentage'] ?? 0);

            if ($productId <= 0) {
                $errors['items'] = 'Select a product for each purchase item.';
                break;
            }
            if ($qty <= 0) {
                $errors['items'] = 'Purchase quantity must be greater than zero.';
                break;
            }
            if ($price < 0) {
                $errors['items'] = 'Purchase price cannot be negative.';
                break;
            }
            if ($gst < 0 || $gst > 100) {
                $errors['items'] = 'GST must be between 0 and 100.';
                break;
            }
        }
    }

    return $errors;
}

/**
 * Extract purchase rows from nested POST arrays.
 *
 * @return array<int, array<string, mixed>>
 */
function extractPurchaseRows(array $post): array
{
    $rawRows = $post['items'] ?? [];
    $rows = [];

    if (!is_array($rawRows)) {
        return [];
    }

    foreach ($rawRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $productId = (int)($row['product_id'] ?? 0);
        $quantity = (float)($row['quantity'] ?? 0);
        $purchasePrice = (float)($row['purchase_price'] ?? 0);
        $gst = (float)($row['gst_percentage'] ?? 0);
        $sellingSnapshot = (float)($row['selling_price_snapshot'] ?? 0);

        if ($productId <= 0 && $quantity <= 0) {
            continue;
        }

        $rows[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'gst_percentage' => $gst,
            'selling_price_snapshot' => $sellingSnapshot,
        ];
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function normalizePurchaseOrderInput(array $post): array
{
    return [
        'supplier_id' => (int)($post['supplier_id'] ?? 0),
        'order_date' => trim((string)($post['order_date'] ?? '')),
        'expected_date' => trim((string)($post['expected_date'] ?? '')) !== '' ? trim((string)$post['expected_date']) : null,
        'status' => (string)($post['status'] ?? 'ordered'),
        'discount' => (float)($post['discount'] ?? 0),
        'notes' => trim((string)($post['notes'] ?? '')) !== '' ? trim((string)$post['notes']) : null,
        'rows' => extractPurchaseRows($post),
    ];
}

/**
 * @return array<string, string>
 */
function emptyPurchaseOrderForm(): array
{
    return [
        'supplier_id' => '',
        'order_date' => date('Y-m-d'),
        'expected_date' => '',
        'status' => 'ordered',
        'discount' => '0',
        'notes' => '',
    ];
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string>
 */
function purchaseOrderFormFromSource(array $source): array
{
    return [
        'supplier_id' => (string)($source['supplier_id'] ?? ''),
        'order_date' => (string)($source['order_date'] ?? date('Y-m-d')),
        'expected_date' => (string)($source['expected_date'] ?? ''),
        'status' => (string)($source['status'] ?? 'ordered'),
        'discount' => isset($source['discount']) ? (string)$source['discount'] : '0',
        'notes' => (string)($source['notes'] ?? ''),
    ];
}
