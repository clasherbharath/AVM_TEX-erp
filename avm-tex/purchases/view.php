<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/procurement.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Purchase Details • A.V.M TEX ERP';
$activeMenu = 'Purchases';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid purchase order selected.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

$order = fetchPurchaseOrderDetails($pdo, $id);
if (!$order) {
    $_SESSION['flash_error'] = 'Purchase order not found.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

$summary = getPurchaseOrderSettlementSummary($pdo, $id) ?? [
    'ordered_quantity' => 0,
    'received_quantity' => 0,
    'received_percent' => 0,
    'paid_total' => 0,
    'balance_due' => (float)$order['grand_total'],
    'payment_status' => 'unpaid',
    'receipt_status' => 'ordered',
    'order_status' => (string)$order['status'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid"><div class="row">
    <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
    <main class="col-12 col-lg-9 col-xl-10 avm-content">
        <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-2 mb-3">
            <div>
                <h2 class="mb-1 fw-bold">Purchase Order <?= htmlspecialchars($order['po_number']) ?></h2>
                <div class="avm-muted">Supplier: <?= htmlspecialchars($order['supplier_name']) ?></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= APP_BASE ?>/purchases/edit.php?id=<?= $id ?>" class="btn btn-outline-primary">Edit</a>
                <a href="<?= APP_BASE ?>/purchases/index.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Subtotal</div><div class="h5 mb-0"><?= format_inr((float)$order['subtotal']) ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">GST</div><div class="h5 mb-0"><?= format_inr((float)$order['gst_total']) ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Grand Total</div><div class="h5 mb-0 text-primary"><?= format_inr((float)$order['grand_total']) ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card avm-card h-100"><div class="card-body"><div class="small avm-muted">Balance Due</div><div class="h5 mb-0 text-danger"><?= format_inr((float)$summary['balance_due']) ?></div></div></div></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card avm-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-semibold">Items</div><span class="badge avm-badge-count"><?= count($order['items']) ?> item(s)</span></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle avm-table mb-0">
                                <thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Purchase</th><th>Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name_snapshot']) ?></td>
                                            <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                            <td><?= number_format((float)$item['received_quantity'], 2) ?></td>
                                            <td><?= format_inr((float)$item['purchase_price']) ?></td>
                                            <td><?= format_inr((float)$item['line_total']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card avm-card mb-3">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Status</div>
                        <div class="mb-2"><span class="badge bg-primary">Order: <?= htmlspecialchars(ucfirst((string)$order['status'])) ?></span></div>
                        <div class="mb-2"><span class="badge bg-<?= $summary['payment_status'] === 'paid' ? 'success' : ($summary['payment_status'] === 'partial' ? 'warning text-dark' : 'danger') ?>">Payment: <?= htmlspecialchars(ucfirst($summary['payment_status'])) ?></span></div>
                        <div><span class="badge bg-<?= $summary['receipt_status'] === 'received' ? 'success' : ($summary['receipt_status'] === 'partial' ? 'warning text-dark' : 'secondary') ?>">Receipt: <?= htmlspecialchars(ucfirst($summary['receipt_status'])) ?></span></div>
                    </div>
                </div>

                <div class="card avm-card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Supplier Payment</div>
                        <?php if ($summary['balance_due'] > 0.01): ?>
                            <form method="post" action="<?= APP_BASE ?>/purchases/payment.php" class="row g-2">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="purchase_order_id" value="<?= $id ?>">
                                <div class="col-12"><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-12"><input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="<?= htmlspecialchars((string)$summary['balance_due']) ?>" placeholder="Amount" required></div>
                                <div class="col-12"><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="other">Other</option></select></div>
                                <div class="col-12"><input type="text" name="reference_number" class="form-control" placeholder="Reference number"></div>
                                <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Notes"></textarea></div>
                                <div class="col-12"><button type="submit" class="btn btn-avm-gold w-100">Record Payment</button></div>
                            </form>
                        <?php else: ?>
                            <div class="text-success fw-semibold">This purchase order is fully paid.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card avm-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Receive Goods</div>
                    <span class="small avm-muted">Receive quantities and update stock ledger</span>
                </div>
                <form method="post" action="<?= APP_BASE ?>/purchases/receive.php">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="purchase_order_id" value="<?= $id ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle avm-table mb-0">
                            <thead><tr><th>Product</th><th>Ordered</th><th>Already Received</th><th>Receive Now</th></tr></thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <?php $remaining = max(0, (float)$item['quantity'] - (float)$item['received_quantity']); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name_snapshot']) ?></td>
                                        <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                        <td><?= number_format((float)$item['received_quantity'], 2) ?></td>
                                        <td>
                                            <input type="number" name="received[<?= (int)$item['id'] ?>]" class="form-control form-control-sm" min="0" max="<?= htmlspecialchars((string)$remaining) ?>" step="0.01" value="<?= htmlspecialchars((string)$remaining) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-avm-green">Post Receipt</button></div>
                </form>
            </div>
        </div>
    </main>
</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
