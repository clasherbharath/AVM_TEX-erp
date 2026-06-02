<?php
/**
 * Shared header + top navbar.
 *
 * Expect:
 * - $pageTitle (string)
 * - $activeMenu (string)
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'A.V.M TEX ERP System';
$activeMenu = $activeMenu ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- App styles -->
    <link href="/avm-tex/assets/css/style.css" rel="stylesheet">
</head>
<body class="avm-bg">
<nav class="navbar navbar-expand-lg navbar-dark avm-navbar shadow-sm">
    <div class="container-fluid">
        <button class="btn btn-outline-light me-2 d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#avmSidebar" aria-controls="avmSidebar">
            Menu
        </button>
        <a class="navbar-brand fw-semibold" href="/avm-tex/dashboard/dashboard.php">
            A.V.M TEX ERP
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="text-white-50 small d-none d-md-block">
                Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
            </div>
            <a class="btn btn-sm btn-avm-gold" href="/avm-tex/auth/logout.php">Logout</a>
        </div>
    </div>
</nav>

