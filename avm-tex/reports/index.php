<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$pageTitle = 'Reports • A.V.M TEX ERP';
$activeMenu = 'Reports';

$stats = [
    'total_revenue' => 0.0,
    'total_invoices' => 0,
    'total_transactions' => 0,
    'total_inventory_items' => 0,
    'low_stock_items' => 0,
    'total_customers' => 0,
];

try {
    $revenueStmt = $pdo->query(
        "SELECT COALESCE(SUM(grand_total), 0) AS revenue FROM invoices WHERE status = 'paid'"
    );
    $stats['total_revenue'] = (float)($revenueStmt->fetchColumn() ?? 0);

    $invoicesStmt = $pdo->query("SELECT COUNT(*) FROM invoices");
    $stats['total_invoices'] = (int)($invoicesStmt->fetchColumn() ?? 0);

    $transactionsStmt = $pdo->query("SELECT COUNT(*) FROM transactions");
    $stats['total_transactions'] = (int)($transactionsStmt->fetchColumn() ?? 0);

    $inventoryStmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    $stats['total_inventory_items'] = (int)($inventoryStmt->fetchColumn() ?? 0);

    $lowStockStmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 10");
    $stats['low_stock_items'] = (int)($lowStockStmt->fetchColumn() ?? 0);

    $customersStmt = $pdo->query("SELECT COUNT(*) FROM customers");
    $stats['total_customers'] = (int)($customersStmt->fetchColumn() ?? 0);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not load statistics.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="mb-3">
                <h2 class="mb-1 fw-bold">Reports & Analytics</h2>
                <div class="avm-muted">Business insights and detailed reporting.</div>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Total Revenue</div>
                            <div class="h4 mb-2 fw-bold text-success"><?= format_inr($stats['total_revenue']) ?></div>
                            <a href="<?= APP_BASE ?>/reports/sales_report.php" class="btn btn-sm btn-outline-primary">View Details</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Invoices</div>
                            <div class="h4 mb-2 fw-bold"><?= $stats['total_invoices'] ?></div>
                            <a href="<?= APP_BASE ?>/billing/index.php" class="btn btn-sm btn-outline-primary">View Invoices</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Transactions</div>
                            <div class="h4 mb-2 fw-bold"><?= $stats['total_transactions'] ?></div>
                            <a href="<?= APP_BASE ?>/transactions/index.php" class="btn btn-sm btn-outline-primary">View Transactions</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Inventory Items</div>
                            <div class="h4 mb-2 fw-bold"><?= $stats['total_inventory_items'] ?></div>
                            <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-sm btn-outline-primary">View Inventory</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Low Stock Alert</div>
                            <div class="h4 mb-2 fw-bold text-warning"><?= $stats['low_stock_items'] ?></div>
                            <a href="<?= APP_BASE ?>/reports/inventory_report.php" class="btn btn-sm btn-outline-warning">View Report</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Customers</div>
                            <div class="h4 mb-2 fw-bold"><?= $stats['total_customers'] ?></div>
                            <a href="<?= APP_BASE ?>/customers/index.php" class="btn btn-sm btn-outline-primary">View Customers</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0">Sales Reports</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="<?= APP_BASE ?>/reports/sales_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Sales Analysis</h6>
                                        <p class="mb-0 small text-muted">Daily, monthly, and yearly sales breakdown</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/customer_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Customer Report</h6>
                                        <p class="mb-0 small text-muted">Purchase history and customer metrics</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0">Financial & Inventory Reports</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="<?= APP_BASE ?>/reports/transaction_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Transaction Report</h6>
                                        <p class="mb-0 small text-muted">Payment methods and transaction analysis</p>
                                    </div>
                                    <span class="badge bg-success rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/inventory_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Inventory Report</h6>
                                        <p class="mb-0 small text-muted">Stock levels and alerts</p>
                                    </div>
                                    <span class="badge bg-success rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/low_stock.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Low Stock Report</h6>
                                        <p class="mb-0 small text-muted">Items at or below their minimum stock threshold.</p>
                                    </div>
                                    <span class="badge bg-warning rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/dead_stock.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Dead Stock Report</h6>
                                        <p class="mb-0 small text-muted">Items with no sales activity in the last 90 days.</p>
                                    </div>
                                    <span class="badge bg-danger rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/stock_valuation.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Stock Valuation</h6>
                                        <p class="mb-0 small text-muted">Inventory value by item and category.</p>
                                    </div>
                                    <span class="badge bg-info rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/ar_reconciliation.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">AR Reconciliation</h6>
                                        <p class="mb-0 small text-muted">Invoice settlement and outstanding balances</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/ap_reconciliation.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">AP Reconciliation</h6>
                                        <p class="mb-0 small text-muted">Purchase orders and supplier payments</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/inventory_history_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Inventory History Report</h6>
                                        <p class="mb-0 small text-muted">Chronological stock movement audit trail</p>
                                    </div>
                                    <span class="badge bg-success rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/product_movement_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Product Movement Report</h6>
                                        <p class="mb-0 small text-muted">Movement totals by product</p>
                                    </div>
                                    <span class="badge bg-success rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/stock_audit_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Stock Audit Report</h6>
                                        <p class="mb-0 small text-muted">Compare current stock against ledger totals</p>
                                    </div>
                                    <span class="badge bg-success rounded-pill">→</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card avm-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0">Procurement Reports</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="<?= APP_BASE ?>/reports/purchase_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Purchase Report</h6>
                                        <p class="mb-0 small text-muted">Procurement totals, receipts, and margin preview</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/supplier_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Supplier Report</h6>
                                        <p class="mb-0 small text-muted">Supplier purchase and outstanding balance summary</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                            <a href="<?= APP_BASE ?>/reports/accounts_payable_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Accounts Payable</h6>
                                        <p class="mb-0 small text-muted">Outstanding supplier liabilities and aging view</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">→</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
