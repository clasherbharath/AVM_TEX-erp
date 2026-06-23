<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../helpers/procurement.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

requireValidCsrfToken('/purchases/index.php');

$purchaseOrderId = (int)($_POST['purchase_order_id'] ?? 0);
$paymentDate = trim((string)($_POST['payment_date'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$paymentMethod = (string)($_POST['payment_method'] ?? 'cash');
$referenceNumber = trim((string)($_POST['reference_number'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($purchaseOrderId <= 0 || $amount <= 0 || $paymentDate === '') {
    $_SESSION['flash_error'] = 'Invalid payment payload.';
    header('Location: ' . APP_BASE . '/purchases/index.php');
    exit;
}

try {
    $allowedMessage = purchasePaymentAllowed($pdo, $purchaseOrderId, $amount);
    if ($allowedMessage !== null) {
        throw new RuntimeException($allowedMessage);
    }

    $pdo->beginTransaction();

    $order = fetchPurchaseOrderDetails($pdo, $purchaseOrderId);
    if (!$order) {
        throw new RuntimeException('Purchase order not found.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO supplier_payments (
            supplier_id, purchase_order_id, payment_date, amount,
            payment_method, reference_number, notes, recorded_by
         ) VALUES (
            :supplier_id, :purchase_order_id, :payment_date, :amount,
            :payment_method, :reference_number, :notes, :recorded_by
         )'
    );
    $stmt->execute([
        ':supplier_id' => (int)$order['supplier_id'],
        ':purchase_order_id' => $purchaseOrderId,
        ':payment_date' => $paymentDate,
        ':amount' => $amount,
        ':payment_method' => $paymentMethod,
        ':reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':recorded_by' => $_SESSION['admin_id'] ?? null,
    ]);

    syncPurchaseOrderState($pdo, $purchaseOrderId);
    $pdo->commit();

    $_SESSION['flash_success'] = 'Supplier payment recorded successfully.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = APP_DEBUG ? $e->getMessage() : 'Could not record payment.';
}

header('Location: ' . APP_BASE . '/purchases/view.php?id=' . $purchaseOrderId);
exit;
