<?php
declare(strict_types=1);

/**
 * Procurement and purchasing helpers.
 */

require_once __DIR__ . '/stock_movement.php';

function generatePurchaseOrderNumber(PDO $pdo): string
{
    $prefix = 'PO-' . date('Ymd') . '-';
    $stmt = $pdo->prepare(
        'SELECT po_number FROM purchase_orders
         WHERE po_number LIKE :prefix
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
 * @param array<int, array<string, mixed>> $rows
 * @return array{
 *   lines: list<array{product_id:int,product_name:string,quantity:float,purchase_price:float,selling_price_snapshot:float,gst_percentage:float,line_subtotal:float,line_gst:float,line_total:float,line_margin:float}>,
 *   subtotal: float,
 *   gst_total: float,
 *   grand_total: float,
 *   gross_margin: float
 * }
 */
function calculatePurchaseTotals(array $rows, float $discount = 0.0): array
{
    $lines = [];
    $subtotal = 0.0;
    $gstTotal = 0.0;
    $grossMargin = 0.0;

    foreach ($rows as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $quantity = (float)($row['quantity'] ?? 0);
        $purchasePrice = (float)($row['purchase_price'] ?? 0);
        $sellingSnapshot = (float)($row['selling_price_snapshot'] ?? 0);
        $gstPct = (float)($row['gst_percentage'] ?? 0);
        $productName = (string)($row['product_name'] ?? '');

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $lineSubtotal = round($quantity * $purchasePrice, 2);
        $lineGst = round($lineSubtotal * ($gstPct / 100), 2);
        $lineTotal = round($lineSubtotal + $lineGst, 2);
        $lineMargin = round(($sellingSnapshot - $purchasePrice) * $quantity, 2);

        $lines[] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'selling_price_snapshot' => $sellingSnapshot,
            'gst_percentage' => $gstPct,
            'line_subtotal' => $lineSubtotal,
            'line_gst' => $lineGst,
            'line_total' => $lineTotal,
            'line_margin' => $lineMargin,
        ];

        $subtotal += $lineSubtotal;
        $gstTotal += $lineGst;
        $grossMargin += $lineMargin;
    }

    $discount = max(0, round($discount, 2));
    $grandTotal = max(0, round($subtotal + $gstTotal - $discount, 2));

    return [
        'lines' => $lines,
        'subtotal' => round($subtotal, 2),
        'gst_total' => round($gstTotal, 2),
        'grand_total' => $grandTotal,
        'gross_margin' => round($grossMargin, 2),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function fetchPurchaseOrderDetails(PDO $pdo, int $purchaseOrderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT po.*, s.supplier_name, s.phone, s.email, s.gst_number AS supplier_gst,
                s.address, s.city, s.state, s.pincode, s.payment_terms
         FROM purchase_orders po
         INNER JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $purchaseOrderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return null;
    }

    $itemsStmt = $pdo->prepare(
        'SELECT pi.*, inv.unit
         FROM purchase_items pi
         INNER JOIN inventory inv ON inv.id = pi.product_id
         WHERE pi.purchase_order_id = :purchase_order_id
         ORDER BY pi.id ASC'
    );
    $itemsStmt->execute([':purchase_order_id' => $purchaseOrderId]);
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentsStmt = $pdo->prepare(
        'SELECT sp.*
         FROM supplier_payments sp
         WHERE sp.purchase_order_id = :purchase_order_id
         ORDER BY sp.payment_date DESC, sp.id DESC'
    );
    $paymentsStmt->execute([':purchase_order_id' => $purchaseOrderId]);
    $order['payments'] = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
}

/**
 * @return array{ordered_quantity: float, received_quantity: float, received_percent: float, paid_total: float, balance_due: float, payment_status: string, receipt_status: string, order_status: string}|null
 */
function getPurchaseOrderSettlementSummary(PDO $pdo, int $purchaseOrderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT po.id, po.status, po.payment_status, po.grand_total,
                COALESCE(items.ordered_quantity, 0) AS ordered_quantity,
                COALESCE(items.received_quantity, 0) AS received_quantity,
                COALESCE(payments.paid_total, 0) AS paid_total
         FROM purchase_orders po
         LEFT JOIN (
             SELECT purchase_order_id,
                    COALESCE(SUM(quantity), 0) AS ordered_quantity,
                    COALESCE(SUM(received_quantity), 0) AS received_quantity
             FROM purchase_items
             GROUP BY purchase_order_id
         ) items ON items.purchase_order_id = po.id
         LEFT JOIN (
             SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS paid_total
             FROM supplier_payments
             GROUP BY purchase_order_id
         ) payments ON payments.purchase_order_id = po.id
         WHERE po.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $purchaseOrderId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$summary) {
        return null;
    }

    $ordered = (float)$summary['ordered_quantity'];
    $received = (float)$summary['received_quantity'];
    $grandTotal = (float)$summary['grand_total'];
    $paid = (float)$summary['paid_total'];

    $receiptStatus = 'ordered';
    if ($received <= 0.01) {
        $receiptStatus = 'ordered';
    } elseif (abs($received - $ordered) <= 0.01) {
        $receiptStatus = 'received';
    } else {
        $receiptStatus = 'partial';
    }

    $paymentStatus = 'unpaid';
    if ($paid <= 0.01) {
        $paymentStatus = 'unpaid';
    } elseif ($paid + 0.01 >= $grandTotal) {
        $paymentStatus = 'paid';
    } else {
        $paymentStatus = 'partial';
    }

    $balanceDue = max(0, round($grandTotal - $paid, 2));

    return [
        'ordered_quantity' => round($ordered, 2),
        'received_quantity' => round($received, 2),
        'received_percent' => $ordered > 0 ? round(($received / $ordered) * 100, 2) : 0.0,
        'paid_total' => round($paid, 2),
        'balance_due' => $balanceDue,
        'payment_status' => $paymentStatus,
        'receipt_status' => $receiptStatus,
        'order_status' => (string)$summary['status'],
    ];
}

function syncPurchaseOrderState(PDO $pdo, int $purchaseOrderId): void
{
    $summary = getPurchaseOrderSettlementSummary($pdo, $purchaseOrderId);
    if ($summary === null) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE purchase_orders SET payment_status = :payment_status, status = :status WHERE id = :id');
    $status = (string)$summary['order_status'];

    if ($status !== 'cancelled') {
        $status = $summary['receipt_status'];
    }

    $stmt->execute([
        ':payment_status' => $summary['payment_status'],
        ':status' => $status,
        ':id' => $purchaseOrderId,
    ]);
}

function purchasePaymentAllowed(PDO $pdo, int $purchaseOrderId, float $amount): ?string
{
    $summary = getPurchaseOrderSettlementSummary($pdo, $purchaseOrderId);
    if ($summary === null) {
        return 'Select a valid purchase order.';
    }

    if ($summary['order_status'] === 'cancelled') {
        return 'Cancelled purchase orders cannot receive payments.';
    }

    if ($amount > $summary['balance_due'] + 0.01) {
        return 'Payment amount exceeds the outstanding balance of ' . number_format($summary['balance_due'], 2) . '.';
    }

    return null;
}

function getSupplierBalanceSummary(PDO $pdo, int $supplierId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(supplier.opening_balance, 0) AS opening_balance,
            COALESCE(purchases.total_purchase, 0) AS total_purchase,
            COALESCE(payments.total_paid, 0) AS total_paid
         FROM suppliers supplier
         LEFT JOIN (
             SELECT supplier_id, COALESCE(SUM(grand_total), 0) AS total_purchase
             FROM purchase_orders
             WHERE supplier_id = :supplier_id_purchase
             GROUP BY supplier_id
         ) purchases
         ON purchases.supplier_id = supplier.id
         LEFT JOIN (
             SELECT po.supplier_id, COALESCE(SUM(sp.amount), 0) AS total_paid
             FROM purchase_orders po
             INNER JOIN supplier_payments sp ON sp.purchase_order_id = po.id
             WHERE po.supplier_id = :supplier_id_payment
             GROUP BY po.supplier_id
         ) payments ON payments.supplier_id = supplier.id
         WHERE supplier.id = :supplier_id_supplier'
    );
    $stmt->execute([
        ':supplier_id_purchase' => $supplierId,
        ':supplier_id_payment' => $supplierId,
        ':supplier_id_supplier' => $supplierId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $openingBalance = (float)($row['opening_balance'] ?? 0);
    $totalPurchase = $openingBalance + (float)($row['total_purchase'] ?? 0);
    $totalPaid = (float)($row['total_paid'] ?? 0);

    return [
        'total_purchase' => round($totalPurchase, 2),
        'total_paid' => round($totalPaid, 2),
        'balance_due' => round(max(0, $totalPurchase - $totalPaid), 2),
    ];
}
