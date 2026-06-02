<?php
/**
 * Middleware: authentication check for protected modules.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . APP_BASE . '/auth/login.php');
    exit;
}

// Ensure a role is present in session for downstream role checks. Try to populate
// from the database if missing (supports legacy `admins` table fallback).
if (empty($_SESSION['admin_role'])) {
    try {
        require_once __DIR__ . '/../config/db.php';
        $id = (int)($_SESSION['admin_id'] ?? 0);
        if ($id > 0) {
            // Try users table first
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['role'])) {
                $_SESSION['admin_role'] = (string)$row['role'];
            } else {
                // Fallback to admins -> treat as full admin
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $id]);
                $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($adminRow) {
                    $_SESSION['admin_role'] = 'admin';
                }
            }
        }
    } catch (Throwable $e) {
        // Non-fatal: leave role missing; downstream pages will treat missing role as unauthorized.
    }
}
