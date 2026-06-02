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

$pageTitle = 'Add User • A.V.M TEX ERP';
$activeMenu = 'Users';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (:username, :password, :role)');
            $stmt->execute([
                ':username' => $username,
                ':password' => $hash,
                ':role' => $role,
            ]);
            $_SESSION['flash_success'] = 'User created.';
            header('Location: ' . APP_BASE . '/users/index.php');
            exit;
        } catch (PDOException $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'Failed to create user.';
        }
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
                <h2 class="mb-1 fw-bold">Add User</h2>
                <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-outline-secondary">← Back</a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card avm-card">
                <div class="card-body">
                    <form method="post">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select">
                                    <option value="staff">Staff</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-avm-gold">Create User</button>
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
