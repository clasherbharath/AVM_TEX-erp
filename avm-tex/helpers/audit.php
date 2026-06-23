<?php
/**
 * Audit logging helper.
 * Provides a small reusable function to record application audit events.
 */
declare(strict_types=1);

/**
 * Log an audit event to the `audit_logs` table.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $action
 * @param string|null $tableName
 * @param int|null $recordId
 * @param string|null $description
 * @return void
 */
function auditLogsHasIpAddress(PDO $pdo): bool
{
    static $hasIp = null;
    if ($hasIp !== null) {
        return $hasIp;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
        $hasIp = $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        $hasIp = false;
    }

    return $hasIp;
}

function logAudit(PDO $pdo, ?int $userId, string $action, ?string $tableName = null, ?int $recordId = null, ?string $description = null, ?string $ipAddress = null): void
{
    try {
        if ($ipAddress === null && PHP_SAPI !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = (string)$_SERVER['REMOTE_ADDR'];
        }

        if (auditLogsHasIpAddress($pdo)) {
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address) VALUES (:user_id, :action, :table_name, :record_id, :description, :ip_address)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':description' => $description,
                ':ip_address' => $ipAddress,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, description) VALUES (:user_id, :action, :table_name, :record_id, :description)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':description' => $description,
            ]);
        }
    } catch (Throwable $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
