<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory_validation.php';

$pageTitle = 'Inventory • A.V.M TEX ERP System';
$activeMenu = 'Inventory';

$search = trim((string)($_GET['q'] ?? ''));
$inventoryRows = [];
$totalProducts = 0;
$totalQuantity = 0.0;
$lowStockCount = 0;
$stockValue = 0.0;
$lowStockRows = [];
$dbError = '';

$threshold = INVENTORY_LOW_STOCK_THRESHOLD;

try {
    $inventoryRows = inventoryFetchRows($pdo, $search);

    $statsStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(quantity), 0) AS total_qty,
            COALESCE(SUM(quantity * purchase_price), 0) AS stock_value,
            SUM(CASE WHEN quantity <= :threshold THEN 1 ELSE 0 END) AS low_stock
         FROM inventory'
    );
    $statsStmt->execute([':threshold' => $threshold]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($stats)) {
        $totalProducts = (int)($stats['total_products'] ?? 0);
        $totalQuantity = (float)($stats['total_qty'] ?? 0);
        $stockValue = (float)($stats['stock_value'] ?? 0);
        $lowStockCount = (int)($stats['low_stock'] ?? 0);
    }

    $lowStmt = $pdo->prepare(
        'SELECT id, product_name, quantity, unit FROM inventory
         WHERE quantity <= :threshold ORDER BY quantity ASC LIMIT 5'
    );
    $lowStmt->execute([':threshold' => $threshold]);
    $lowStockRows = $lowStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($lowStockRows)) {
        $lowStockRows = [];
    }
} catch (PDOException $e) {
    $dbError = APP_DEBUG
        ? $e->getMessage()
        : 'Could not load inventory. Import sql/avm_tex_inventory.sql';
    $inventoryRows = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">

            <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Inventory Management</h2>
                    <div class="avm-muted">Module 2 — Track textile stock, prices, and suppliers.</div>
                </div>
                <a href="<?= APP_BASE ?>/inventory/add.php" class="btn btn-avm-gold">+ Add Item</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <?php if ($dbError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>

            <?php if ($lowStockCount > 0 && $dbError === ''): ?>
            <div class="alert alert-warning border-0 shadow-sm mb-3" role="alert">
                <strong>Low stock alert:</strong> <?= $lowStockCount ?> item(s) at or below <?= $threshold ?> units.
                <?php if ($lowStockRows !== []): ?>
                    <ul class="mb-0 mt-2 small">
                        <?php foreach ($lowStockRows as $lowRow): ?>
                            <?php if (!is_array($lowRow)) {
                                continue;
                            } ?>
                            <li>
                                <?= htmlspecialchars((string)($lowRow['product_name'] ?? '')) ?> —
                                <span class="fw-semibold text-danger">
                                    <?= number_format((float)($lowRow['quantity'] ?? 0), 2) ?>
                                    <?= htmlspecialchars((string)($lowRow['unit'] ?? '')) ?>
                                </span>
                                <a href="<?= APP_BASE ?>/inventory/stock_update.php?id=<?= (int)($lowRow['id'] ?? 0) ?>" class="ms-1">Update stock</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Total Products</div>
                            <div class="h3 avm-metric mb-0"><?= $totalProducts ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Total Quantity</div>
                            <div class="h3 avm-metric mb-0"><?= number_format($totalQuantity, 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Low Stock Items</div>
                            <div class="h3 avm-metric mb-0 text-danger"><?= $lowStockCount ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card avm-card h-100">
                        <div class="card-body">
                            <div class="small avm-muted">Stock Value (Purchase)</div>
                            <div class="h3 avm-metric mb-0">₹ <?= number_format($stockValue, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card avm-card mb-3">
                <div class="card-body">
                    <form method="get" action="<?= APP_BASE ?>/inventory/index.php" class="row g-2 align-items-end">
                        <div class="col-12 col-md-8 col-lg-9">
                            <label for="q" class="form-label">Search inventory</label>
                            <input type="search" name="q" id="q" class="form-control"
                                   placeholder="Product, category, supplier, barcode, unit..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-avm-green flex-grow-1">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-semibold">Inventory List</div>
                        <span class="badge avm-badge-count"><?= count($inventoryRows) ?> item(s)</span>
                    </div>

                    <?php if ($dbError === '' && count($inventoryRows) === 0): ?>
                        <div class="text-center py-5 avm-muted">
                            <div class="fs-5 mb-2">No inventory items found</div>
                            <?php if ($search !== ''): ?>
                                <p class="mb-3">No results for &ldquo;<?= htmlspecialchars($search) ?>&rdquo;.</p>
                                <a href="<?= APP_BASE ?>/inventory/index.php" class="btn btn-outline-secondary btn-sm">View all</a>
                            <?php else: ?>
                                <a href="<?= APP_BASE ?>/inventory/add.php" class="btn btn-avm-gold btn-sm">Add First Item</a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($dbError === ''): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle avm-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th class="d-none d-md-table-cell">Purchase Price</th>
                                        <th class="d-none d-md-table-cell">Selling Price</th>
                                        <th class="d-none d-lg-table-cell">Supplier</th>
                                        <th>Stock Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventoryRows as $index => $row): ?>
                                        <?php
                                        if (!is_array($row)) {
                                            continue;
                                        }
                                        $qty = (float)($row['quantity'] ?? 0);
                                        $status = inventoryStockStatus($qty, $threshold);
                                        ?>
                                        <tr>
                                            <td class="avm-muted"><?= $index + 1 ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars((string)($row['product_name'] ?? '')) ?></div>
                                                <?php if (!empty($row['barcode'])): ?>
                                                    <div class="small avm-muted"><?= htmlspecialchars((string)$row['barcode']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars((string)($row['category'] ?? '')) ?></td>
                                            <td>
                                                <?= number_format($qty, 2) ?>
                                                <?= htmlspecialchars((string)($row['unit'] ?? '')) ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">₹ <?= number_format((float)($row['purchase_price'] ?? 0), 2) ?></td>
                                            <td class="d-none d-md-table-cell">₹ <?= number_format((float)($row['selling_price'] ?? 0), 2) ?></td>
                                            <td class="d-none d-lg-table-cell">
                                                <?= !empty($row['supplier']) ? htmlspecialchars((string)$row['supplier']) : '<span class="avm-muted">—</span>' ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= htmlspecialchars($status['class']) ?>">
                                                    <?= htmlspecialchars($status['label']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm flex-wrap">
                                                    <a href="<?= APP_BASE ?>/inventory/stock_update.php?id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-outline-success">Stock</a>
                                                    <a href="<?= APP_BASE ?>/inventory/edit.php?id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-outline-dark">Edit</a>
                                                    <button type="button" class="btn btn-outline-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteInventoryModal"
                                                            data-id="<?= (int)($row['id'] ?? 0) ?>"
                                                            data-name="<?= htmlspecialchars((string)($row['product_name'] ?? ''), ENT_QUOTES) ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="deleteInventoryModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content avm-modal">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Delete this inventory item?</p>
                            <p class="fw-semibold text-danger mb-0" id="deleteInventoryName"></p>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" action="<?= APP_BASE ?>/inventory/delete.php">
                                <input type="hidden" name="id" id="deleteInventoryId" value="">
                                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
document.getElementById('deleteInventoryModal')?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    document.getElementById('deleteInventoryId').value = button.getAttribute('data-id') || '';
    document.getElementById('deleteInventoryName').textContent = button.getAttribute('data-name') || '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
