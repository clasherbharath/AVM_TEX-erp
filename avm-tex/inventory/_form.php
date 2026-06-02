<?php
/**
 * Shared inventory form — field names match MySQL columns.
 */
declare(strict_types=1);

$formAction = $formAction ?? (APP_BASE . '/inventory/add.php');
$submitLabel = $submitLabel ?? 'Save Item';
$hiddenId = $hiddenId ?? null;
$showQuantity = $showQuantity ?? true;

$categories = ['Fabric', 'Yarn', 'Accessory', 'Garment', 'General'];
$units = ['pcs', 'meter', 'kg', 'roll', 'box', 'bundle'];

$fieldError = static function (array $errors, string $field): string {
    return isset($errors[$field]) ? ' is-invalid' : '';
};
?>

<form method="post" action="<?= htmlspecialchars($formAction) ?>" class="avm-customer-form" novalidate>
    <?php if ($hiddenId !== null): ?>
        <input type="hidden" name="id" value="<?= (int)$hiddenId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="product_name" id="product_name"
                   class="form-control<?= $fieldError($errors, 'product_name') ?>"
                   value="<?= htmlspecialchars($form['product_name']) ?>" maxlength="200" required>
            <?php if (!empty($errors['product_name'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['product_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
            <select name="category" id="category" class="form-select<?= $fieldError($errors, 'category') ?>" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($form['category'] === $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['category'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['category']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($showQuantity): ?>
        <div class="col-12 col-md-4">
            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
            <input type="number" name="quantity" id="quantity" step="0.01" min="0"
                   class="form-control<?= $fieldError($errors, 'quantity') ?>"
                   value="<?= htmlspecialchars($form['quantity']) ?>" required>
            <?php if (!empty($errors['quantity'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['quantity']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="col-12 col-md-4">
            <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
            <select name="unit" id="unit" class="form-select<?= $fieldError($errors, 'unit') ?>" required>
                <?php foreach ($units as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= ($form['unit'] === $u) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-4">
            <label for="barcode" class="form-label">Barcode</label>
            <input type="text" name="barcode" id="barcode" class="form-control"
                   value="<?= htmlspecialchars($form['barcode']) ?>" maxlength="50" placeholder="Optional">
        </div>

        <div class="col-12 col-md-4">
            <label for="purchase_price" class="form-label">Purchase Price (₹) <span class="text-danger">*</span></label>
            <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0"
                   class="form-control<?= $fieldError($errors, 'purchase_price') ?>"
                   value="<?= htmlspecialchars($form['purchase_price']) ?>" required>
            <?php if (!empty($errors['purchase_price'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['purchase_price']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
            <label for="selling_price" class="form-label">Selling Price (₹) <span class="text-danger">*</span></label>
            <input type="number" name="selling_price" id="selling_price" step="0.01" min="0"
                   class="form-control<?= $fieldError($errors, 'selling_price') ?>"
                   value="<?= htmlspecialchars($form['selling_price']) ?>" required>
            <?php if (!empty($errors['selling_price'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['selling_price']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
            <label for="gst_percentage" class="form-label">GST % <span class="text-danger">*</span></label>
            <input type="number" name="gst_percentage" id="gst_percentage" step="0.01" min="0" max="100"
                   class="form-control<?= $fieldError($errors, 'gst_percentage') ?>"
                   value="<?= htmlspecialchars($form['gst_percentage']) ?>" required>
            <?php if (!empty($errors['gst_percentage'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['gst_percentage']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="supplier" class="form-label">Supplier</label>
            <input type="text" name="supplier" id="supplier" class="form-control"
                   value="<?= htmlspecialchars($form['supplier']) ?>" maxlength="150" placeholder="Supplier name">
        </div>

        <?php if (!empty($item['updated_at'] ?? null)): ?>
        <div class="col-12 col-md-6 d-flex align-items-end">
            <div class="w-100 p-3 rounded avm-form-hint">
                <div class="small fw-semibold mb-1">Last Updated</div>
                <div class="small avm-muted">
                    <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$item['updated_at']))) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-2 border-top">
        <button type="submit" class="btn btn-avm-gold"><?= htmlspecialchars($submitLabel) ?></button>
        <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
