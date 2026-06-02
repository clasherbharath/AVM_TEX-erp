<?php
/**
 * Invoice number generation and billing calculations.
 */
declare(strict_types=1);

function generateInvoiceNumber(PDO $pdo): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare(
        'SELECT invoice_number FROM invoices
         WHERE invoice_number LIKE :prefix
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $sequence = 1;
    if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches)) {
        $sequence = (int)$matches[1] + 1;
    }

    return $prefix . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate line and invoice totals from posted rows.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array{
 *   lines: list<array{product_id:int,quantity:float,price:float,gst_percentage:float,line_subtotal:float,line_gst:float,total:float}>,
 *   subtotal: float,
 *   gst_total: float,
 *   grand_total: float
 * }
 */
function calculateInvoiceTotals(array $rows, float $discount): array
{
    $lines = [];
    $subtotal = 0.0;
    $gstTotal = 0.0;

    foreach ($rows as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $qty = (float)($row['quantity'] ?? 0);
        $price = (float)($row['price'] ?? 0);
        $gstPct = (float)($row['gst_percentage'] ?? 0);

        if ($productId <= 0 || $qty <= 0) {
            continue;
        }

        $lineSubtotal = round($qty * $price, 2);
        $lineGst = round($lineSubtotal * ($gstPct / 100), 2);
        $lineTotal = round($lineSubtotal + $lineGst, 2);

        $lines[] = [
            'product_id' => $productId,
            'quantity' => $qty,
            'price' => $price,
            'gst_percentage' => $gstPct,
            'line_subtotal' => $lineSubtotal,
            'line_gst' => $lineGst,
            'total' => $lineTotal,
        ];

        $subtotal += $lineSubtotal;
        $gstTotal += $lineGst;
    }

    $discount = max(0, round($discount, 2));
    $grandTotal = max(0, round($subtotal + $gstTotal - $discount, 2));

    return [
        'lines' => $lines,
        'subtotal' => round($subtotal, 2),
        'gst_total' => round($gstTotal, 2),
        'grand_total' => $grandTotal,
    ];
}

/**
 * Fetch invoice header + customer + line items.
 *
 * @return array<string, mixed>|null
 */
function fetchInvoiceDetails(PDO $pdo, int $invoiceId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT i.*, c.customer_name, c.phone, c.email, c.address, c.city, c.state,
                c.pincode, c.gst_number AS customer_gst
         FROM invoices i
         INNER JOIN customers c ON c.id = i.customer_id
         WHERE i.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        return null;
    }

    $itemsStmt = $pdo->prepare(
        'SELECT ii.*, inv.product_name, inv.unit
         FROM invoice_items ii
         INNER JOIN inventory inv ON inv.id = ii.product_id
         WHERE ii.invoice_id = :invoice_id
         ORDER BY ii.id ASC'
    );
    $itemsStmt->execute([':invoice_id' => $invoiceId]);
    $invoice['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    return $invoice;
}
