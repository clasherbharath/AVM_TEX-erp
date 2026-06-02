<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/invoice_helper.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Invalid invoice.');
}

$invoice = fetchInvoiceDetails($pdo, $id);
if (!$invoice) {
    exit('Invoice not found.');
}

$items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; padding: 24px; }
        .inv-header { border-bottom: 3px solid #0f3d2e; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { color: #0f3d2e; font-weight: 700; font-size: 1.5rem; }
        .gold-line { color: #6b1022; }
        table th { background: #f7f1e1; border-color: #c9a227 !important; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print mb-3 d-flex gap-2">
        <button onclick="window.print()" class="btn btn-dark btn-sm">Print</button>
        <a href="<?= APP_BASE ?>/billing/invoice_view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="inv-header d-flex justify-content-between align-items-start">
        <div>
            <div class="brand">A.V.M TEX ERP</div>
            <div class="small gold-line">Premium Textile Billing</div>
            <div class="small text-muted mt-1">Kerala Inspired Textile Business</div>
        </div>
        <div class="text-end">
            <h4 class="mb-1">TAX INVOICE</h4>
            <div><strong>#</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></div>
            <div><strong>Date:</strong> <?= htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars(ucfirst($invoice['status'])) ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <h6 class="text-uppercase text-muted">Bill To</h6>
            <p class="mb-0 fw-bold"><?= htmlspecialchars($invoice['customer_name']) ?></p>
            <p class="mb-0"><?= htmlspecialchars($invoice['phone'] ?? '') ?></p>
            <p class="mb-0"><?= htmlspecialchars($invoice['address'] ?? '') ?></p>
            <p class="mb-0">
                <?= htmlspecialchars(trim(($invoice['city'] ?? '') . ', ' . ($invoice['state'] ?? '') . ' ' . ($invoice['pincode'] ?? ''), ', ')) ?>
            </p>
            <?php if (!empty($invoice['customer_gst'])): ?>
                <p class="mb-0"><strong>GSTIN:</strong> <?= htmlspecialchars($invoice['customer_gst']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>GST %</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $line): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($line['product_name']) ?> (<?= htmlspecialchars($line['unit']) ?>)</td>
                    <td><?= number_format((float)$line['quantity'], 2) ?></td>
                    <td>₹ <?= number_format((float)$line['price'], 2) ?></td>
                    <td><?= number_format((float)$line['gst_percentage'], 2) ?>%</td>
                    <td class="text-end">₹ <?= number_format((float)$line['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row justify-content-end mt-3">
        <div class="col-5">
            <table class="table table-sm">
                <tr><td>Subtotal</td><td class="text-end">₹ <?= number_format((float)$invoice['subtotal'], 2) ?></td></tr>
                <tr><td>GST Total</td><td class="text-end">₹ <?= number_format((float)$invoice['gst_total'], 2) ?></td></tr>
                <tr><td>Discount</td><td class="text-end">- ₹ <?= number_format((float)$invoice['discount'], 2) ?></td></tr>
                <tr class="fw-bold fs-5"><td>Grand Total</td><td class="text-end">₹ <?= number_format((float)$invoice['grand_total'], 2) ?></td></tr>
            </table>
        </div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
        <p class="mt-3 small"><strong>Notes:</strong> <?= htmlspecialchars($invoice['notes']) ?></p>
    <?php endif; ?>

    <p class="text-center text-muted small mt-5 mb-0">Thank you for your business — A.V.M TEX</p>
</body>
</html>
