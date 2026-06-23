<?php
/**
 * Shared transaction form partial.
 * Expects:
 * - string $formAction
 * - string $submitLabel
 * - array<string, mixed> $form
 * - array<string, string> $errors
 * - array<int, array<string, mixed>> $invoiceOptions
 * - int|null $transactionId
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/security.php';
$transactionId = $transactionId ?? null;
$formAction = $formAction ?? APP_BASE . '/transactions/add.php';
$submitLabel = $submitLabel ?? 'Save Transaction';
?>
<form method="post" action="<?= $formAction ?>">
    <?= csrfTokenInput() ?>
    <?php if ($transactionId !== null): ?>
        <input type="hidden" name="id" value="<?= (int)$transactionId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Transaction Type</label>
            <select name="transaction_type" class="form-select <?= isset($errors['transaction_type']) ? 'is-invalid' : '' ?>">
                <option value="payment" <?= $form['transaction_type'] === 'payment' ? 'selected' : '' ?>>Payment</option>
                <option value="refund" <?= $form['transaction_type'] === 'refund' ? 'selected' : '' ?>>Refund</option>
                <option value="adjustment" <?= $form['transaction_type'] === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                <option value="credit_memo" <?= $form['transaction_type'] === 'credit_memo' ? 'selected' : '' ?>>Credit Memo</option>
            </select>
            <?php if (!empty($errors['transaction_type'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['transaction_type']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Invoice</label>
            <select name="invoice_id" class="form-select <?= isset($errors['invoice_id']) ? 'is-invalid' : '' ?>">
                <option value="">— Select Invoice —</option>
                <?php foreach ($invoiceOptions as $invoice): ?>
                    <option value="<?= (int)$invoice['id'] ?>" <?= (string)$form['invoice_id'] === (string)$invoice['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($invoice['invoice_number'] . ' • ' . $invoice['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['invoice_id'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['invoice_id']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Reference Number</label>
            <input type="text" name="reference_number" class="form-control <?= isset($errors['reference_number']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($form['reference_number']) ?>" maxlength="100">
            <?php if (!empty($errors['reference_number'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['reference_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Transaction Date</label>
            <input type="date" name="transaction_date" class="form-control <?= isset($errors['transaction_date']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($form['transaction_date']) ?>">
            <?php if (!empty($errors['transaction_date'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['transaction_date']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($form['amount']) ?>">
            <?php if (!empty($errors['amount'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="form-select <?= isset($errors['payment_method']) ? 'is-invalid' : '' ?>">
                <option value="cash" <?= $form['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="cheque" <?= $form['payment_method'] === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                <option value="bank_transfer" <?= $form['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                <option value="card" <?= $form['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
                <option value="other" <?= $form['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            <?php if (!empty($errors['payment_method'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['payment_method']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Bank Name</label>
            <input type="text" name="bank_name" class="form-control <?= isset($errors['bank_name']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($form['bank_name']) ?>">
            <?php if (!empty($errors['bank_name'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['bank_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">Cheque Number</label>
            <input type="text" name="cheque_number" class="form-control <?= isset($errors['cheque_number']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($form['cheque_number']) ?>">
            <?php if (!empty($errors['cheque_number'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['cheque_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="transaction_notes" rows="4" class="form-control <?= isset($errors['transaction_notes']) ? 'is-invalid' : '' ?>"><?= htmlspecialchars($form['transaction_notes']) ?></textarea>
            <?php if (!empty($errors['transaction_notes'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['transaction_notes']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center">
            <a href="<?= APP_BASE ?>/transactions/index.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-avm-gold"><?= htmlspecialchars($submitLabel) ?></button>
        </div>
    </div>
</form>
