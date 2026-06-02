<?php
/**
 * Shared sidebar navigation.
 *
 * Expect:
 * - $activeMenu (string)
 */

declare(strict_types=1);
$activeMenu = $activeMenu ?? 'Dashboard';

$role = $_SESSION['admin_role'] ?? 'staff';
$navItems = [
    'Dashboard' => '/avm-tex/dashboard/dashboard.php',
    'Customers' => '/avm-tex/customers/index.php',
    'Billing' => '/avm-tex/billing/index.php',
    'Inventory' => '/avm-tex/inventory/index.php',
    'Transactions' => '/avm-tex/transactions/index.php',
    'Reports' => '/avm-tex/reports/index.php',
];

if ($role === 'admin') {
    $navItems['Users'] = '/avm-tex/users/index.php';
    $navItems['Settings'] = '/avm-tex/settings/index.php';
}
?>

<!-- Offcanvas for mobile + static sidebar for desktop -->
<div class="offcanvas-lg offcanvas-start avm-sidebar" tabindex="-1" id="avmSidebar" aria-labelledby="avmSidebarLabel">
    <div class="offcanvas-header border-bottom border-light border-opacity-10">
        <h5 class="offcanvas-title text-white" id="avmSidebarLabel">Navigation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#avmSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="p-3">
            <div class="avm-brand-badge mb-3">
                <div class="fw-semibold text-white">A.V.M TEX</div>
                <div class="small text-white-50">Premium Textile ERP</div>
            </div>

            <ul class="nav nav-pills flex-column gap-1">
                <?php foreach ($navItems as $label => $href): ?>
                    <?php $isActive = ($activeMenu === $label); ?>
                    <li class="nav-item">
                        <a class="nav-link avm-navlink <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($href) ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

