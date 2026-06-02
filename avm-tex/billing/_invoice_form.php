<?php
declare(strict_types=1);

/** @var array<int, array<string, mixed>> $customerList */
/** @var string $invoiceNumber */
/** @var array<string, mixed>|null $old */

$old = $old ?? ($_SESSION['invoice_form_old'] ?? null);
unset($_SESSION['invoice_form_old']);

$customerId = (int)($old['customer_id'] ?? 0);
$invoiceDate = (string)($old['invoice_date'] ?? date('Y-m-d'));
$discount = (string)($old['discount'] ?? '0');
$status = (string)($old['status'] ?? 'pending');
$notes = (string)($old['notes'] ?? '');
$oldItems = [];

if (is_array($old['items'] ?? null)) {
    $oldItems = $old['items'];
} elseif (is_array($old['product_id'] ?? null)) {
    $productIds = array_values($old['product_id']);
    $quantities = array_values(is_array($old['quantity'] ?? null) ? $old['quantity'] : []);
    $prices = array_values(is_array($old['price'] ?? null) ? $old['price'] : []);
    $gstValues = array_values(is_array($old['gst'] ?? null) ? $old['gst'] : []);

    foreach ($productIds as $idx => $productId) {
        $oldItems[] = [
            'product_id' => (int)$productId,
            'quantity' => $quantities[$idx] ?? '1',
            'price' => $prices[$idx] ?? '0',
            'gst_percentage' => $gstValues[$idx] ?? '0',
        ];
    }
}
?>

<form method="post" action="<?= APP_BASE ?>/billing/save_invoice.php" id="invoiceForm">
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <label class="form-label">Invoice Number</label>
            <input type="text" name="invoice_number" class="form-control" readonly
                   value="<?= htmlspecialchars($invoiceNumber) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Customer <span class="text-danger">*</span></label>
            <select name="customer_id" class="form-select" required>
                <option value="">Select customer</option>
                <?php foreach ($customerList as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['customer_name']) ?> — <?= htmlspecialchars($c['phone'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
            <input type="date" name="invoice_date" class="form-control" required
                   value="<?= htmlspecialchars($invoiceDate) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional"
                   value="<?= htmlspecialchars($notes) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-semibold">Invoice Items</h5>
        <button type="button" class="btn btn-sm btn-avm-green" id="addInvoiceRow">+ Add Item</button>
    </div>

    <div class="table-responsive mb-3">
        <table class="table avm-table align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="width:110px">Qty</th>
                    <th style="width:130px">Price (₹)</th>
                    <th style="width:100px">GST %</th>
                    <th>Subtotal</th>
                    <th>Line Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="invoiceItemsBody"></tbody>
        </table>
    </div>

    <template id="invoiceRowTemplate">
        <tr>
            <td>
                <select name="product_id[]" class="form-select item-product" required></select>
                <div class="small text-danger stock-warn d-none mt-1">Exceeds available stock</div>
            </td>
            <td><input type="number" name="quantity[]" class="form-control item-qty" min="0.01" step="0.01" value="1" required></td>
            <td><input type="number" name="price[]" class="form-control item-price" min="0" step="0.01" required></td>
            <td><input type="number" name="gst[]" class="form-control item-gst" min="0" max="100" step="0.01" required></td>
            <td class="line-subtotal">₹ 0.00</td>
            <td class="line-total fw-semibold">₹ 0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">×</button></td>
        </tr>
    </template>

    <div class="row justify-content-end">
        <div class="col-12 col-md-5">
            <div class="card avm-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="avm-muted">Subtotal</span>
                        <span id="summarySubtotal">₹ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="avm-muted">GST Total</span>
                        <span id="summaryGst">₹ 0.00</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount (₹)</label>
                        <input type="number" name="discount" id="discount" class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars($discount) ?>">
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="fw-bold">Grand Total</span>
                        <span class="fw-bold fs-5" id="summaryGrand">₹ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-avm-gold btn-lg">Save Invoice</button>
        <a href="<?= APP_BASE ?>/billing/index.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
    </div>
</form>

<script>
window.AVM_INVENTORY_PRODUCTS = <?= json_encode($productList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.AVM_INVOICE_OLD_ITEMS = <?= json_encode(array_values($oldItems), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
