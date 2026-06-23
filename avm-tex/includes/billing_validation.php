<?php
/**
 * Billing / invoice validation.
 */
declare(strict_types=1);

require_once __DIR__ . '/../helpers/invoice_helper.php';

/**
 * @param array<string, mixed> $post
 * @return array{errors: array<string, string>, rows: array<int, array<string, mixed>>, discount: float, customer_id: int, invoice_date: string, status: string}
 */
function validateInvoicePost(PDO $pdo, array $post): array
{
    $errors = [];
    $rows = [];
    $qtyByProduct = [];
    $customerId = (int)($post['customer_id'] ?? 0);
    $invoiceDate = trim((string)($post['invoice_date'] ?? ''));
    $status = (string)($post['status'] ?? 'pending');
    $discount = (float)($post['discount'] ?? 0);

    if ($customerId <= 0) {
        $errors['customer_id'] = 'Please select a customer.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
        $chk->execute([':id' => $customerId]);
        if (!$chk->fetch()) {
            $errors['customer_id'] = 'Selected customer does not exist.';
        }
    }

    if ($invoiceDate === '' || strtotime($invoiceDate) === false) {
        $errors['invoice_date'] = 'Valid invoice date is required.';
    }

    if (!in_array($status, ['paid', 'pending', 'cancelled'], true)) {
        $errors['status'] = 'Invalid invoice status.';
    }

    if ($discount < 0) {
        $errors['discount'] = 'Discount cannot be negative.';
    }

    $productIds = is_array($post['product_id'] ?? null) ? $post['product_id'] : [];
    $quantities = is_array($post['quantity'] ?? null) ? $post['quantity'] : [];
    $prices = is_array($post['price'] ?? null) ? $post['price'] : [];
    $gstPercentages = is_array($post['gst'] ?? null) ? $post['gst'] : [];
    $rawRows = $post['items'] ?? [];

    if (
        $productIds !== [] ||
        $quantities !== [] ||
        $prices !== [] ||
        $gstPercentages !== []
    ) {
        if (
            count($productIds) !== count($quantities) ||
            count($productIds) !== count($prices) ||
            count($productIds) !== count($gstPercentages)
        ) {
            $errors['items'] = 'Invoice item fields must all have matching indexes.';
        }

        $count = max(
            count($productIds),
            count($quantities),
            count($prices),
            count($gstPercentages)
        );

        for ($idx = 0; $idx < $count; $idx++) {
            $productId = (int)($productIds[$idx] ?? 0);
            $qty = (float)($quantities[$idx] ?? 0);
            $price = (float)($prices[$idx] ?? 0);
            $gst = (float)($gstPercentages[$idx] ?? 0);

            if ($productId <= 0 && $qty <= 0) {
                continue;
            }

            if ($productId <= 0) {
                $errors['items'] = 'Select a product for each line item.';
                break;
            }
            if ($qty <= 0) {
                $errors['items'] = 'Quantity must be greater than zero.';
                break;
            }
            if ($price < 0) {
                $errors['items'] = 'Price cannot be negative.';
                break;
            }

            $qtyByProduct[$productId] = ($qtyByProduct[$productId] ?? 0) + $qty;
            $rows[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'price' => $price,
                'gst_percentage' => $gst,
            ];
        }
    } elseif (is_array($rawRows)) {
        foreach ($rawRows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $productId = (int)($row['product_id'] ?? 0);
            $qty = (float)($row['quantity'] ?? 0);
            $price = (float)($row['price'] ?? 0);
            $gst = (float)($row['gst_percentage'] ?? 0);

            if ($productId <= 0 && $qty <= 0) {
                continue;
            }

            if ($productId <= 0) {
                $errors['items'] = 'Select a product for each line item.';
                break;
            }
            if ($qty <= 0) {
                $errors['items'] = 'Quantity must be greater than zero.';
                break;
            }
            if ($price < 0) {
                $errors['items'] = 'Price cannot be negative.';
                break;
            }

            $qtyByProduct[$productId] = ($qtyByProduct[$productId] ?? 0) + $qty;
            $rows[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'price' => $price,
                'gst_percentage' => $gst,
            ];
        }
    }

    if ($rows === [] && !isset($errors['items'])) {
        $errors['items'] = 'Add at least one valid line item.';
    }

    if ($qtyByProduct !== []) {
        $placeholders = [];
        $params = [];

        foreach (array_values(array_keys($qtyByProduct)) as $index => $productId) {
            $placeholder = ':product_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $productId;
        }

        $inventoryStmt = $pdo->prepare(
            'SELECT id, product_name, quantity FROM inventory WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        $inventoryStmt->execute($params);

        /** @var array<int, array<string, mixed>> $inventoryMap */
        $inventoryMap = [];
        foreach ($inventoryStmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $inventoryMap[(int)$product['id']] = $product;
        }

        foreach ($qtyByProduct as $productId => $neededQty) {
            $product = $inventoryMap[$productId] ?? null;

            if (!$product) {
                $errors['items'] = 'One or more products are invalid.';
                break;
            }

            if ((float)$product['quantity'] < $neededQty) {
                $errors['items'] = sprintf(
                    'Insufficient stock for "%s". Available: %s, requested: %s',
                    $product['product_name'],
                    $product['quantity'],
                    $neededQty
                );
                break;
            }
        }
    }

    return [
        'errors' => $errors,
        'rows' => $rows,
        'discount' => $discount,
        'customer_id' => $customerId,
        'invoice_date' => $invoiceDate,
        'status' => $status,
    ];
}
