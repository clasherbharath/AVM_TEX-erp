<?php
/**
 * Shared customer form — field names match MySQL columns exactly.
 */
declare(strict_types=1);

$formAction = $formAction ?? (APP_BASE . '/customers/add.php');
$submitLabel = $submitLabel ?? 'Save Customer';
$hiddenId = $hiddenId ?? null;

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
            <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
            <input
                type="text"
                name="customer_name"
                id="customer_name"
                class="form-control<?= $fieldError($errors, 'customer_name') ?>"
                value="<?= htmlspecialchars($form['customer_name']) ?>"
                placeholder="e.g. Kerala Handloom Traders"
                maxlength="150"
                required
            >
            <?php if (!empty($errors['customer_name'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['customer_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
            <input
                type="tel"
                name="phone"
                id="phone"
                class="form-control<?= $fieldError($errors, 'phone') ?>"
                value="<?= htmlspecialchars($form['phone']) ?>"
                placeholder="10-digit phone number"
                maxlength="10"
                pattern="[6-9][0-9]{9}"
                required
            >
            <?php if (!empty($errors['phone'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php else: ?>
                <div class="form-text">Indian phone: starts with 6–9, 10 digits.</div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="email" class="form-label">Email</label>
            <input
                type="email"
                name="email"
                id="email"
                class="form-control<?= $fieldError($errors, 'email') ?>"
                value="<?= htmlspecialchars($form['email']) ?>"
                placeholder="customer@example.com"
            >
            <?php if (!empty($errors['email'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <label for="gst_number" class="form-label">GST Number <span class="avm-muted">(optional)</span></label>
            <input
                type="text"
                name="gst_number"
                id="gst_number"
                class="form-control text-uppercase<?= $fieldError($errors, 'gst_number') ?>"
                value="<?= htmlspecialchars($form['gst_number']) ?>"
                placeholder="15-character GSTIN"
                maxlength="15"
            >
            <?php if (!empty($errors['gst_number'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['gst_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
            <textarea
                name="address"
                id="address"
                class="form-control<?= $fieldError($errors, 'address') ?>"
                rows="3"
                placeholder="Street, area, landmark"
                required
            ><?= htmlspecialchars($form['address']) ?></textarea>
            <?php if (!empty($errors['address'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['address']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
            <label for="city" class="form-label">City</label>
            <input
                type="text"
                name="city"
                id="city"
                class="form-control<?= $fieldError($errors, 'city') ?>"
                value="<?= htmlspecialchars($form['city']) ?>"
                placeholder="City"
            >
        </div>

        <div class="col-12 col-md-4">
            <label for="state" class="form-label">State</label>
            <input
                type="text"
                name="state"
                id="state"
                class="form-control<?= $fieldError($errors, 'state') ?>"
                value="<?= htmlspecialchars($form['state']) ?>"
                placeholder="State"
            >
        </div>

        <div class="col-12 col-md-4">
            <label for="pincode" class="form-label">Pincode</label>
            <input
                type="text"
                name="pincode"
                id="pincode"
                class="form-control<?= $fieldError($errors, 'pincode') ?>"
                value="<?= htmlspecialchars($form['pincode']) ?>"
                placeholder="6-digit pincode"
                maxlength="6"
                pattern="\d{6}"
            >
            <?php if (!empty($errors['pincode'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['pincode']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6 d-flex align-items-end">
            <div class="w-100 p-3 rounded avm-form-hint">
                <div class="small fw-semibold mb-1">Created At</div>
                <div class="small avm-muted">
                    <?php if (!empty($customer['created_at'] ?? null)): ?>
                        <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$customer['created_at']))) ?>
                        <span class="d-block">(auto-recorded, cannot be edited)</span>
                    <?php else: ?>
                        Will be recorded automatically when you save.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-2 border-top">
        <button type="submit" class="btn btn-avm-gold"><?= htmlspecialchars($submitLabel) ?></button>
        <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
