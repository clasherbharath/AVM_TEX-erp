<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/invoice_helper.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid invoice.';
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

$invoice = fetchInvoiceDetails($pdo, $id);
if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

$pageTitle = 'Invoice ' . $invoice['invoice_number'] . ' • A.V.M TEX ERP';
$activeMenu = 'Billing';
$items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$statusClass = match ($invoice['status']) {
    'paid' => 'bg-success',
    'cancelled' => 'bg-secondary',
    default => 'bg-warning text-dark',
};
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Invoice Details</h2>
                    <div class="avm-muted"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= APP_BASE ?>/billing/print_invoice.php?id=<?= $id ?>" class="btn btn-avm-gold" target="_blank">Print Invoice</a>
                    <a href="<?= APP_BASE ?>/billing/download_invoice.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">Download PDF</a>
                    <a href="<?= APP_BASE ?>/billing/index.php" class="btn btn-outline-secondary">← Back</a>
                </div>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-uppercase avm-muted mb-1">A.V.M TEX ERP</div>
                            <div class="fw-bold fs-5">Tax Invoice</div>
                            <div class="mt-2">
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($invoice['status'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></div>
                            <div><strong>Date:</strong> <?= htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) ?></div>
                            <div class="small avm-muted">Created: <?= htmlspecialchars(date('d M Y, h:i A', strtotime($invoice['created_at']))) ?></div>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">Bill To</div>
                            <div class="fw-bold"><?= htmlspecialchars($invoice['customer_name']) ?></div>
                            <?php if (!empty($invoice['phone'])): ?>
                                <div>Phone: <?= htmlspecialchars($invoice['phone']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($invoice['email'])): ?>
                                <div>Email: <?= htmlspecialchars($invoice['email']) ?></div>
                            <?php endif; ?>
                            <div><?= htmlspecialchars($invoice['address'] ?? '') ?></div>
                            <div>
                                <?= htmlspecialchars(trim(($invoice['city'] ?? '') . ', ' . ($invoice['state'] ?? '') . ' ' . ($invoice['pincode'] ?? ''), ', ')) ?>
                            </div>
                            <?php if (!empty($invoice['customer_gst'])): ?>
                                <div class="mt-1"><span class="badge avm-gst-badge">GST: <?= htmlspecialchars($invoice['customer_gst']) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table avm-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>GST %</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $line): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($line['product_name']) ?></div>
                                            <div class="small avm-muted"><?= htmlspecialchars($line['unit']) ?></div>
                                        </td>
                                        <td><?= number_format((float)$line['quantity'], 2) ?></td>
                                        <td>₹ <?= number_format((float)$line['price'], 2) ?></td>
                                        <td><?= number_format((float)$line['gst_percentage'], 2) ?>%</td>
                                        <td class="text-end fw-semibold">₹ <?= number_format((float)$line['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="card avm-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span><span>₹ <?= number_format((float)$invoice['subtotal'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>GST Total</span><span>₹ <?= number_format((float)$invoice['gst_total'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Discount</span><span>- ₹ <?= number_format((float)$invoice['discount'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 fw-bold fs-5">
                                <span>Grand Total</span><span>₹ <?= number_format((float)$invoice['grand_total'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($invoice['notes'])): ?>
                <div class="alert alert-light border mt-3 mb-0">
                    <strong>Notes:</strong> <?= htmlspecialchars($invoice['notes']) ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
