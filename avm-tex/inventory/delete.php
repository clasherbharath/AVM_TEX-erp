<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid inventory item.';
    header('Location: ' . APP_BASE . '/inventory/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT product_name FROM inventory WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        $_SESSION['flash_error'] = 'Item not found or already deleted.';
    } else {
        $del = $pdo->prepare('DELETE FROM inventory WHERE id = :id');
        $del->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Deleted "' . $row['product_name'] . '" from inventory.';
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG
        ? $e->getMessage()
        : 'Could not delete inventory item.';
}

header('Location: ' . APP_BASE . '/inventory/index.php');
exit;
