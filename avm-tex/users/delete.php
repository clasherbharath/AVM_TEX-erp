<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

requireAdminRole('/users/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

requireValidCsrfToken('/users/index.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user id.';
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

$currentUserId = (int)($_SESSION['admin_id'] ?? 0);
if ($currentUserId > 0 && $id === $currentUserId) {
    $_SESSION['flash_error'] = 'You cannot delete the account currently in use.';
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

try {
    $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
    $roleStmt->execute([':id' => $id]);
    $targetRole = $roleStmt->fetchColumn();

    if ($targetRole === false) {
        $_SESSION['flash_error'] = 'User not found or already deleted.';
        header('Location: ' . APP_BASE . '/users/index.php');
        exit;
    }

    if ($targetRole === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            $_SESSION['flash_error'] = 'At least one admin account must remain active.';
            header('Location: ' . APP_BASE . '/users/index.php');
            exit;
        }
    }

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $_SESSION['flash_success'] = 'User deleted.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Failed to delete user.';
}

header('Location: ' . APP_BASE . '/users/index.php');
exit;
