<?php
/**
 * Inventory form validation helpers.
 */
declare(strict_types=1);

/** Quantity at or below this value triggers low-stock alert. */
const INVENTORY_LOW_STOCK_THRESHOLD = 10;

/**
 * @return array<string, string>
 */
function validateInventoryInput(array $data, bool $requireQuantity = true): array
{
    $errors = [];

    $productName = trim((string)($data['product_name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $unit = trim((string)($data['unit'] ?? ''));
    $supplier = trim((string)($data['supplier'] ?? ''));
    $barcode = trim((string)($data['barcode'] ?? ''));

    $quantityRaw = (string)($data['quantity'] ?? '');
    $purchaseRaw = (string)($data['purchase_price'] ?? '');
    $sellingRaw = (string)($data['selling_price'] ?? '');
    $gstRaw = (string)($data['gst_percentage'] ?? '');

    if ($productName === '') {
        $errors['product_name'] = 'Product name is required.';
    } elseif (mb_strlen($productName) > 200) {
        $errors['product_name'] = 'Product name cannot exceed 200 characters.';
    }

    if ($category === '') {
        $errors['category'] = 'Category is required.';
    }

    if ($unit === '') {
        $errors['unit'] = 'Unit is required.';
    }

    if ($requireQuantity) {
        if ($quantityRaw === '' || !is_numeric($quantityRaw)) {
            $errors['quantity'] = 'Quantity must be a valid number.';
        } elseif ((float)$quantityRaw < 0) {
            $errors['quantity'] = 'Quantity cannot be negative.';
        }
    }

    if ($purchaseRaw === '' || !is_numeric($purchaseRaw)) {
        $errors['purchase_price'] = 'Purchase price must be numeric.';
    } elseif ((float)$purchaseRaw < 0) {
        $errors['purchase_price'] = 'Purchase price cannot be negative.';
    }

    if ($sellingRaw === '' || !is_numeric($sellingRaw)) {
        $errors['selling_price'] = 'Selling price must be numeric.';
    } elseif ((float)$sellingRaw < 0) {
        $errors['selling_price'] = 'Selling price cannot be negative.';
    }

    if ($gstRaw === '' || !is_numeric($gstRaw)) {
        $errors['gst_percentage'] = 'GST percentage must be numeric.';
    } elseif ((float)$gstRaw < 0 || (float)$gstRaw > 100) {
        $errors['gst_percentage'] = 'GST percentage must be between 0 and 100.';
    }

    return $errors;
}

/**
 * @return array<string, mixed>
 */
function normalizeInventoryInput(array $data): array
{
    return [
        'product_name' => trim((string)($data['product_name'] ?? '')),
        'category' => trim((string)($data['category'] ?? '')),
        'quantity' => (float)($data['quantity'] ?? 0),
        'unit' => trim((string)($data['unit'] ?? 'pcs')),
        'purchase_price' => (float)($data['purchase_price'] ?? 0),
        'selling_price' => (float)($data['selling_price'] ?? 0),
        'supplier' => trim((string)($data['supplier'] ?? '')) !== '' ? trim((string)$data['supplier']) : null,
        'gst_percentage' => (float)($data['gst_percentage'] ?? 0),
        'barcode' => trim((string)($data['barcode'] ?? '')) !== '' ? trim((string)$data['barcode']) : null,
    ];
}

/**
 * @return array<string, string>
 */
function emptyInventoryForm(): array
{
    return [
        'product_name' => '',
        'category' => 'Fabric',
        'quantity' => '0',
        'unit' => 'pcs',
        'purchase_price' => '0',
        'selling_price' => '0',
        'supplier' => '',
        'gst_percentage' => '5',
        'barcode' => '',
    ];
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string>
 */
function inventoryFormFromSource(array $source): array
{
    return [
        'product_name' => (string)($source['product_name'] ?? ''),
        'category' => (string)($source['category'] ?? ''),
        'quantity' => (string)($source['quantity'] ?? '0'),
        'unit' => (string)($source['unit'] ?? 'pcs'),
        'purchase_price' => (string)($source['purchase_price'] ?? '0'),
        'selling_price' => (string)($source['selling_price'] ?? '0'),
        'supplier' => (string)($source['supplier'] ?? ''),
        'gst_percentage' => (string)($source['gst_percentage'] ?? '0'),
        'barcode' => (string)($source['barcode'] ?? ''),
    ];
}

/**
 * Validate stock adjustment form.
 *
 * @return array<string, string>
 */
function validateStockAdjustment(array $data, float $currentQty): array
{
    $errors = [];
    $action = (string)($data['stock_action'] ?? '');
    $qtyRaw = (string)($data['adjust_qty'] ?? '');

    if (!in_array($action, ['add', 'subtract', 'set'], true)) {
        $errors['stock_action'] = 'Invalid stock action.';
    }

    if ($qtyRaw === '' || !is_numeric($qtyRaw)) {
        $errors['adjust_qty'] = 'Quantity must be a valid number.';
    } elseif ((float)$qtyRaw < 0) {
        $errors['adjust_qty'] = 'Quantity cannot be negative.';
    } else {
        $qty = (float)$qtyRaw;
        if ($action === 'subtract' && $qty > $currentQty) {
            $errors['adjust_qty'] = 'Cannot subtract more than current stock (' . $currentQty . ').';
        }
        if ($action === 'set' && $qty < 0) {
            $errors['adjust_qty'] = 'Quantity cannot be negative.';
        }
    }

    return $errors;
}

/**
 * @return float New quantity after adjustment
 */
function applyStockAdjustment(float $currentQty, string $action, float $adjustQty): float
{
    return match ($action) {
        'add' => $currentQty + $adjustQty,
        'subtract' => max(0, $currentQty - $adjustQty),
        'set' => max(0, $adjustQty),
        default => $currentQty,
    };
}

/**
 * Fetch inventory rows as a list of associative arrays (PDO).
 *
 * @return list<array<string, mixed>>
 */
function inventoryFetchRows(PDO $pdo, string $search = ''): array
{
    $sql = 'SELECT id, product_name, category, quantity, unit, purchase_price, selling_price,
                   supplier, gst_percentage, barcode, created_at, updated_at
            FROM inventory';

    if ($search === '') {
        $stmt = $pdo->query($sql . ' ORDER BY product_name ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $like = '%' . $search . '%';
        $stmt = $pdo->prepare(
            $sql . ' WHERE product_name LIKE :q_name
                OR category LIKE :q_category
                OR COALESCE(supplier, \'\') LIKE :q_supplier
                OR COALESCE(barcode, \'\') LIKE :q_barcode
                OR unit LIKE :q_unit
             ORDER BY product_name ASC'
        );
        $stmt->execute([
            ':q_name' => $like,
            ':q_category' => $like,
            ':q_supplier' => $like,
            ':q_barcode' => $like,
            ':q_unit' => $like,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return is_array($rows) ? $rows : [];
}

/**
 * Stock status label + Bootstrap badge class.
 *
 * @return array{label: string, class: string}
 */
function inventoryStockStatus(float $quantity, int $threshold = INVENTORY_LOW_STOCK_THRESHOLD): array
{
    if ($quantity <= 0) {
        return ['label' => 'Out of Stock', 'class' => 'bg-danger'];
    }
    if ($quantity <= $threshold) {
        return ['label' => 'Low Stock', 'class' => 'bg-warning text-dark'];
    }
    return ['label' => 'In Stock', 'class' => 'bg-success'];
}
