<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

session_start();

// If already logged in, go straight to dashboard.
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
    exit;
}

$error = '';
$username = '';
$debugInfo = '';

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    $error = 'Database connection failed. Start MySQL in XAMPP and import sql/avm_tex_admins.sql.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            // Prefer new `users` table (supports roles). If missing, fall back to legacy `admins` table.
            $user = null;
            try {
                $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $inner) {
                // users table may not exist or the schema may differ; fallback to legacy `admins` table.
                if (APP_DEBUG) {
                    $debugInfo = 'Users table query failed: ' . $inner->getMessage();
                }
            }

            $checkedAdminFallback = false;

            if ($user) {
                if (password_verify($password, (string)$user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$user['id'];
                    $_SESSION['admin_username'] = (string)$user['username'];
                    $_SESSION['admin_role'] = (string)($user['role'] ?? 'staff');

                    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
                    exit;
                }
                $checkedAdminFallback = true;
                if (APP_DEBUG) {
                    $debugInfo = 'User found in users table; password mismatch. Falling back to legacy admins.';
                }
            }

            if (!$user || $checkedAdminFallback) {
                // Legacy admins table fallback for old installations or bad users hashes.
                $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($admin && password_verify($password, (string)$admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_username'] = (string)$admin['username'];
                    $_SESSION['admin_role'] = 'admin';

                    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
                    exit;
                }

                $error = 'Invalid username or password.';
                if (APP_DEBUG) {
                    if ($admin) {
                        $debugInfo = $debugInfo !== ''
                            ? $debugInfo . ' Legacy admin record found; password mismatch.'
                            : 'Legacy admin record found; password mismatch.';
                    } else {
                        $debugInfo = $debugInfo !== ''
                            ? $debugInfo . ' No matching user found in users or admins tables.'
                            : 'No matching user found in users or admins tables.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Login failed: database error.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login • A.V.M TEX ERP System</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_BASE ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="avm-bg">
<div class="container avm-login-wrap py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-lg-10 col-xl-9">
            <div class="avm-login-card">
                <div class="row g-0">
                    <div class="col-md-6 avm-login-hero p-4 p-lg-5 position-relative">
                        <div class="position-relative" style="z-index:1;">
                            <div class="avm-hero-badge mb-3">
                                <span class="rounded-circle" style="width:10px;height:10px;background:var(--avm-gold);display:inline-block;"></span>
                                Premium Textile ERP
                            </div>
                            <h1 class="h3 fw-bold mb-2">A.V.M TEX ERP System</h1>
                            <p class="mb-4 text-white-50">
                                Secure admin access for billing, inventory, transactions, and reporting.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6 p-4 p-lg-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="text-uppercase small avm-muted">Admin Portal</div>
                                <div class="h4 mb-0 fw-bold">Sign in</div>
                            </div>
                        </div>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <?php if ($debugInfo !== ''): ?>
                                    <div class="small text-muted mt-2"><?= htmlspecialchars($debugInfo) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="mt-2" <?= $error !== '' && str_contains($error, 'Database') ? 'style="opacity:.6;pointer-events:none"' : '' ?>>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control form-control-lg" autocomplete="username"
                                       value="<?= htmlspecialchars($username) ?>" placeholder="Enter username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password"
                                       placeholder="Enter password" required>
                            </div>

                            <button type="submit" class="btn btn-avm-gold btn-lg w-100">
                                Login
                            </button>

                            <div class="mt-3 small avm-muted">
                                Default: <span class="fw-semibold">admin</span> / <span class="fw-semibold">admin123</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_BASE ?>/assets/js/app.js"></script>
</body>
</html>
