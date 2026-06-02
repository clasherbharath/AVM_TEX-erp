<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Only allow POST to prevent accidental deletes via URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/customers/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid customer selected for deletion.';
    header('Location: ' . APP_BASE . '/customers/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT customer_name FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        $_SESSION['flash_error'] = 'Customer not found or already deleted.';
        header('Location: ' . APP_BASE . '/customers/index.php');
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM customers WHERE id = :id');
    $delete->execute([':id' => $id]);

    $_SESSION['flash_success'] = 'Customer "' . $customer['customer_name'] . '" deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Could not delete customer. Please try again.';
}

header('Location: ' . APP_BASE . '/customers/index.php');
exit;
