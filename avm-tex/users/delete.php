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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user id.';
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $_SESSION['flash_success'] = 'User deleted.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Failed to delete user.';
}

header('Location: ' . APP_BASE . '/users/index.php');
exit;
