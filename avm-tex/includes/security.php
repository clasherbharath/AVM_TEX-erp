<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfTokenInput(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrfToken(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($sessionToken)
        && $sessionToken !== ''
        && is_string($token)
        && hash_equals($sessionToken, $token);
}

function requireValidCsrfToken(string $redirectPath, string $message = 'Your session expired. Please try again.'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['_csrf'] ?? null;
    if (isValidCsrfToken(is_string($token) ? $token : null)) {
        return;
    }

    $_SESSION['flash_error'] = $message;
    header('Location: ' . APP_BASE . $redirectPath);
    exit;
}

function currentUserIsAdmin(): bool
{
    return !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

function requireAdminRole(string $redirectPath = '/dashboard/dashboard.php'): void
{
    if (currentUserIsAdmin()) {
        return;
    }

    $_SESSION['flash_error'] = 'Unauthorized: admin access required.';
    header('Location: ' . APP_BASE . $redirectPath);
    exit;
}
