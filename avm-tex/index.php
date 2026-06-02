<?php
/**
 * Application entry point.
 * Redirects to login or dashboard based on session.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
    exit;
}

header('Location: ' . APP_BASE . '/auth/login.php');
exit;
