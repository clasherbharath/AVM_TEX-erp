<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Role check
if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    $_SESSION['flash_error'] = 'Unauthorized: admin access required.';
    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
    exit;
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user id.';
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

$pageTitle = 'Edit User • A.V.M TEX ERP';
$activeMenu = 'Users';

$error = '';
try {
    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Unable to load user.';
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';
    $password = $_POST['password'] ?? '';

    try {
        $setParts = ['role = :role'];
        $params = [':role' => $role, ':id' => $id];

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $setParts[] = 'password = :password';
            $params[':password'] = $hash;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash_success'] = 'User updated.';
        header('Location: ' . APP_BASE . '/users/index.php');
        exit;
    } catch (PDOException $e) {
        $error = APP_DEBUG ? $e->getMessage() : 'Failed to update user.';
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <h2 class="mb-1 fw-bold">Edit User</h2>
                <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-outline-secondary">← Back</a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card avm-card">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">New Password (leave blank to keep)</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select">
                                    <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-avm-gold">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
