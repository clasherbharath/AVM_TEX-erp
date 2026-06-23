<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/procurement.php';

requireAdminRole('/suppliers/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/suppliers/index.php');
    exit;
}

requireValidCsrfToken('/suppliers/index.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier selected.';
    header('Location: ' . APP_BASE . '/suppliers/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT supplier_name FROM suppliers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['flash_error'] = 'Supplier not found or already deleted.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :id');
        $check->execute([':id' => $id]);
        $summary = getSupplierBalanceSummary($pdo, $id);

        if ((int)$check->fetchColumn() > 0 || (float)($summary['balance_due'] ?? 0) > 0.01) {
            throw new RuntimeException('Cannot delete supplier with purchase history or outstanding balance. Set them inactive or keep the record for audit trail.');
        }

        $del = $pdo->prepare('DELETE FROM suppliers WHERE id = :id');
        $del->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Deleted "' . $row['supplier_name'] . '" from suppliers.';
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete supplier.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not delete supplier.';
}

header('Location: ' . APP_BASE . '/suppliers/index.php');
exit;
