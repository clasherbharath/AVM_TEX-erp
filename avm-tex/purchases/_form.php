<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

/** @var array<string, string> $form */
/** @var array<string, string> $errors */
/** @var list<array<string, mixed>> $purchaseRows */
/** @var list<array<string, mixed>> $suppliers */
/** @var list<array<string, mixed>> $products */

$formAction = $formAction ?? (APP_BASE . '/purchases/add.php');
$submitLabel = $submitLabel ?? 'Save Purchase Order';
$hiddenId = $hiddenId ?? null;
$purchaseRows = $purchaseRows ?? [];
$suppliers = $suppliers ?? [];
$products = $products ?? [];

?>

<script>
window.AVM_PURCHASE_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.AVM_PURCHASE_OLD_ITEMS = <?= json_encode($purchaseRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<form method="post" action="<?= htmlspecialchars($formAction) ?>" novalidate>
    <?= csrfTokenInput() ?>
    <?php if ($hiddenId !== null): ?>
        <input type="hidden" name="id" value="<?= (int)$hiddenId ?>">
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
            <select name="supplier_id" id="supplier_id" class="form-select<?= isset($errors['supplier_id']) ? ' is-invalid' : '' ?>" required>
                <option value="">Select supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int)$supplier['id'] ?>" <?= (string)$form['supplier_id'] === (string)$supplier['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($supplier['supplier_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['supplier_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['supplier_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-6 col-md-3">
            <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
            <input type="date" name="order_date" id="order_date" class="form-control<?= isset($errors['order_date']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($form['order_date']) ?>" required>
            <?php if (!empty($errors['order_date'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['order_date']) ?></div><?php endif; ?>
        </div>
        <div class="col-6 col-md-3">
            <label for="expected_date" class="form-label">Expected Date</label>
            <input type="date" name="expected_date" id="expected_date" class="form-control<?= isset($errors['expected_date']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($form['expected_date']) ?>">
            <?php if (!empty($errors['expected_date'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['expected_date']) ?></div><?php endif; ?>
        </div>
        <div class="col-12 col-md-4">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select<?= isset($errors['status']) ? ' is-invalid' : '' ?>">
                <?php foreach (['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Partial', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label for="discount" class="form-label">Discount (₹)</label>
            <input type="number" name="discount" id="discount" step="0.01" min="0" class="form-control<?= isset($errors['discount']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($form['discount']) ?>">
            <?php if (!empty($errors['discount'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['discount']) ?></div><?php endif; ?>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Gross Margin Preview</label>
            <div class="form-control bg-light" id="summaryMargin">₹ 0.00</div>
        </div>
        <div class="col-12">
            <label for="notes" class="form-label">Notes</label>
            <textarea name="notes" id="notes" rows="3" class="form-control"><?= htmlspecialchars($form['notes']) ?></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-bold">Purchase Items</h5>
        <button type="button" id="addPurchaseRow" class="btn btn-outline-primary btn-sm">+ Add Item</button>
    </div>

    <?php if (!empty($errors['items'])): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($errors['items']) ?></div>
    <?php endif; ?>

    <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle avm-table">
            <thead>
                <tr>
                    <th style="min-width: 240px;">Product</th>
                    <th style="min-width: 100px;">Qty</th>
                    <th style="min-width: 130px;">Purchase Price</th>
                    <th style="min-width: 100px;">GST %</th>
                    <th style="min-width: 120px;">Selling Snapshot</th>
                    <th>Subtotal</th>
                    <th>Line GST</th>
                    <th>Total</th>
                    <th>Margin</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="purchaseItemsBody"></tbody>
        </table>
    </div>

    <template id="purchaseRowTemplate">
        <tr>
            <td>
                <select class="form-select purchase-product" name="items[ROW_INDEX][product_id]" required></select>
            </td>
            <td><input type="number" class="form-control purchase-qty" name="items[ROW_INDEX][quantity]" step="0.01" min="0" value="1" required></td>
            <td><input type="number" class="form-control purchase-price" name="items[ROW_INDEX][purchase_price]" step="0.01" min="0" value="0" required></td>
            <td><input type="number" class="form-control purchase-gst" name="items[ROW_INDEX][gst_percentage]" step="0.01" min="0" max="100" value="0" required></td>
            <td><input type="number" class="form-control purchase-selling" name="items[ROW_INDEX][selling_price_snapshot]" step="0.01" min="0" value="0"></td>
            <td class="line-subtotal">₹ 0.00</td>
            <td class="line-gst">₹ 0.00</td>
            <td class="line-total">₹ 0.00</td>
            <td class="line-margin">₹ 0.00</td>
            <td><button type="button" class="btn btn-outline-danger btn-sm remove-row">Remove</button></td>
        </tr>
    </template>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 offset-md-6">
            <div class="card avm-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong id="summarySubtotal">₹ 0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>GST</span><strong id="summaryGst">₹ 0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Discount</span><strong id="summaryDiscount">₹ 0.00</strong></div>
                    <div class="d-flex justify-content-between fs-5"><span>Grand Total</span><strong id="summaryGrand">₹ 0.00</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-2 border-top">
        <button type="submit" class="btn btn-avm-gold"><?= htmlspecialchars($submitLabel) ?></button>
        <a href="<?= APP_BASE ?>/purchases/index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script src="<?= APP_BASE ?>/assets/js/purchases.js"></script>
