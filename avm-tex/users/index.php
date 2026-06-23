<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

// Role check
if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    $_SESSION['flash_error'] = 'Unauthorized: admin access required.';
    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
    exit;
}

$pageTitle = 'User Management • A.V.M TEX ERP';
$activeMenu = 'Users';

// Fetch users
try {
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id ASC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Unable to load users.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <h2 class="mb-1 fw-bold">User Management</h2>
                <a href="<?= APP_BASE ?>/users/add.php" class="btn btn-avm-gold">+ Add User</a>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>

            <div class="card avm-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $i => $u): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($u['username']) ?></td>
                                        <td><?= htmlspecialchars($u['role']) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
                                        <td class="text-end">
                                            <a href="<?= APP_BASE ?>/users/edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                            <form method="post" action="<?= APP_BASE ?>/users/delete.php" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                                <?= csrfTokenInput() ?>
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
