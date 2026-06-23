<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/audit.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Audit logout event if an admin user is present.
logAudit($pdo ?? null, isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null, 'logout', 'users', isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null, 'User logged out');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}

session_destroy();

header('Location: ' . APP_BASE . '/auth/login.php');
exit;
