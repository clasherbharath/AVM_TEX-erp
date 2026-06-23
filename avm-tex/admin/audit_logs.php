<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/format_currency.php';

requireAdminRole('/dashboard/dashboard.php');

$pageTitle = 'Audit Logs • A.V.M TEX ERP';
$activeMenu = 'Audit Logs';

// Filters
$filterStart = trim((string)($_GET['start_date'] ?? ''));
$filterEnd = trim((string)($_GET['end_date'] ?? ''));
$filterUser = trim((string)($_GET['user_id'] ?? ''));
$filterAction = trim((string)($_GET['action'] ?? ''));
$filterEntity = trim((string)($_GET['entity'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$filterClauses = ['1 = 1'];
$params = [];

if ($filterStart !== '') {
    $filterClauses[] = 'created_at >= :start_date';
    $params[':start_date'] = $filterStart . ' 00:00:00';
}
if ($filterEnd !== '') {
    $filterClauses[] = 'created_at <= :end_date';
    $params[':end_date'] = $filterEnd . ' 23:59:59';
}
if ($filterUser !== '') {
    $filterClauses[] = 'user_id = :user_id';
    $params[':user_id'] = (int)$filterUser;
}
if ($filterAction !== '') {
    $filterClauses[] = 'action LIKE :action';
    $params[':action'] = '%' . $filterAction . '%';
}
if ($filterEntity !== '') {
    $filterClauses[] = 'table_name LIKE :entity';
    $params[':entity'] = '%' . $filterEntity . '%';
}
if ($search !== '') {
    $filterClauses[] = '(action LIKE :search OR table_name LIKE :search OR description LIKE :search OR ip_address LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $filterClauses);

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE ' . $whereSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();

    $querySql = 'SELECT al.id, al.user_id, u.username, al.action, al.table_name, al.record_id, al.ip_address, al.description, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE ' . $whereSql . '
        ORDER BY al.created_at DESC, al.id DESC';

    if (!$exportCsv) {
        $querySql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
    }

    $stmt = $pdo->prepare($querySql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Unable to load audit logs.';
    $auditLogs = [];
    $totalRows = 0;
}

if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['ID', 'User ID', 'Username', 'Action', 'Entity Type', 'Entity ID', 'IP Address', 'Description', 'Timestamp']);
    foreach ($auditLogs as $row) {
        fputcsv($out, [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['action'],
            $row['table_name'],
            $row['record_id'],
            $row['ip_address'],
            $row['description'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$totalPages = (int)ceil($totalRows / $perPage);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start justify-content-between mb-3 gap-3">
                <div>
                    <h2 class="mb-1 fw-bold">Audit Logs</h2>
                    <div class="avm-muted">Review and export system audit events. Admin-only access.</div>
                </div>
                <a href="?export=csv&start_date=<?= htmlspecialchars($filterStart) ?>&end_date=<?= htmlspecialchars($filterEnd) ?>&user_id=<?= htmlspecialchars($filterUser) ?>&action=<?= htmlspecialchars($filterAction) ?>&entity=<?= htmlspecialchars($filterEntity) ?>&search=<?= htmlspecialchars($search) ?>" class="btn btn-outline-secondary btn-sm">Export CSV</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <div class="card avm-card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($filterStart) ?>" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($filterEnd) ?>" class="form-control">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">User</label>
                            <input type="text" name="user_id" value="<?= htmlspecialchars($filterUser) ?>" class="form-control" placeholder="User ID">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Action</label>
                            <input type="text" name="action" value="<?= htmlspecialchars($filterAction) ?>" class="form-control" placeholder="payment, invoice_create">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Entity</label>
                            <input type="text" name="entity" value="<?= htmlspecialchars($filterEntity) ?>" class="form-control" placeholder="invoices, transactions">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Search</label>
                            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search action, entity, IP, description">
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end justify-content-end">
                            <button type="submit" class="btn btn-avm-gold me-2">Apply Filters</button>
                            <a href="<?= APP_BASE ?>/admin/audit_logs.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card avm-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Entity ID</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                    <tr><td colspan="7" class="text-center py-4 avm-muted">No audit logs found for the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?= (int)$log['id'] ?></td>
                                            <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                            <td><?= htmlspecialchars($log['action']) ?></td>
                                            <td><?= htmlspecialchars($log['table_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($log['record_id'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="Audit log pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&start_date=<?= htmlspecialchars($filterStart) ?>&end_date=<?= htmlspecialchars($filterEnd) ?>&user_id=<?= htmlspecialchars($filterUser) ?>&action=<?= htmlspecialchars($filterAction) ?>&entity=<?= htmlspecialchars($filterEntity) ?>&search=<?= htmlspecialchars($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
