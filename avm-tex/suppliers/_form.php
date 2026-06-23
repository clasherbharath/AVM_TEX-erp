<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

/** @var array<string, string> $form */
/** @var array<string, string> $errors */

$formAction = $formAction ?? (APP_BASE . '/suppliers/add.php');
$submitLabel = $submitLabel ?? 'Save Supplier';
$hiddenId = $hiddenId ?? null;

$fieldError = static function (array $errors, string $field): string {
    return isset($errors[$field]) ? ' is-invalid' : '';
};
?>

<form method="post" action="<?= htmlspecialchars($formAction) ?>" class="avm-customer-form" novalidate>
    <?= csrfTokenInput() ?>
    <?php if ($hiddenId !== null): ?>
        <input type="hidden" name="id" value="<?= (int)$hiddenId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="supplier_name" class="form-label">Supplier Name <span class="text-danger">*</span></label>
            <input type="text" name="supplier_name" id="supplier_name"
                   class="form-control<?= $fieldError($errors, 'supplier_name') ?>"
                   value="<?= htmlspecialchars($form['supplier_name']) ?>" maxlength="150" required>
            <?php if (!empty($errors['supplier_name'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['supplier_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="contact_person" class="form-label">Contact Person</label>
            <input type="text" name="contact_person" id="contact_person" class="form-control"
                   value="<?= htmlspecialchars($form['contact_person']) ?>" maxlength="150">
        </div>

        <div class="col-12 col-md-4">
            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
            <input type="text" name="phone" id="phone"
                   class="form-control<?= $fieldError($errors, 'phone') ?>"
                   value="<?= htmlspecialchars($form['phone']) ?>" maxlength="20" required>
            <?php if (!empty($errors['phone'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" class="form-control"
                   value="<?= htmlspecialchars($form['email']) ?>" maxlength="191">
        </div>

        <div class="col-12 col-md-4">
            <label for="gst_number" class="form-label">GST Number</label>
            <input type="text" name="gst_number" id="gst_number" class="form-control"
                   value="<?= htmlspecialchars($form['gst_number']) ?>" maxlength="64">
            <?php if (!empty($errors['gst_number'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['gst_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
            <textarea name="address" id="address" rows="3" class="form-control<?= $fieldError($errors, 'address') ?>" required><?= htmlspecialchars($form['address']) ?></textarea>
            <?php if (!empty($errors['address'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['address']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
            <label for="city" class="form-label">City</label>
            <input type="text" name="city" id="city" class="form-control" value="<?= htmlspecialchars($form['city']) ?>" maxlength="100">
        </div>

        <div class="col-12 col-md-4">
            <label for="state" class="form-label">State</label>
            <input type="text" name="state" id="state" class="form-control" value="<?= htmlspecialchars($form['state']) ?>" maxlength="100">
        </div>

        <div class="col-12 col-md-4">
            <label for="pincode" class="form-label">Pincode</label>
            <input type="text" name="pincode" id="pincode" class="form-control" value="<?= htmlspecialchars($form['pincode']) ?>" maxlength="20">
            <?php if (!empty($errors['pincode'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['pincode']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="payment_terms" class="form-label">Payment Terms</label>
            <input type="text" name="payment_terms" id="payment_terms" class="form-control"
                   value="<?= htmlspecialchars($form['payment_terms']) ?>" maxlength="100" placeholder="e.g. 30 days">
        </div>

        <div class="col-12 col-md-6">
            <label for="opening_balance" class="form-label">Opening Balance (₹)</label>
            <input type="number" name="opening_balance" id="opening_balance" step="0.01" min="0"
                   class="form-control<?= $fieldError($errors, 'opening_balance') ?>"
                   value="<?= htmlspecialchars($form['opening_balance']) ?>">
            <?php if (!empty($errors['opening_balance'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['opening_balance']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-2 border-top">
        <button type="submit" class="btn btn-avm-gold"><?= htmlspecialchars($submitLabel) ?></button>
        <a href="<?= APP_BASE ?>/suppliers/index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
