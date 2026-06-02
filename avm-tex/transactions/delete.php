<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid transaction selected for deletion.';
    header('Location: ' . APP_BASE . '/transactions/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $_SESSION['flash_success'] = 'Transaction deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = APP_DEBUG
        ? 'Database error: ' . $e->getMessage()
        : 'Failed to delete the transaction. Please try again.';
}

header('Location: ' . APP_BASE . '/transactions/index.php');
exit;
