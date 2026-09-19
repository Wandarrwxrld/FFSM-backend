<?php
require_once __DIR__ . '/Database.php';

class AuditLogger
{
    /**
     * @param int|null $userId  Null for actions taken before a user exists
     *                          in the request context (e.g. a failed login
     *                          attempt against an unknown email).
     * @param string   $action  Short verb: 'login', 'logout', 'signup',
     *                          'password changed', 'password recovered',
     *                          'create', 'update', 'delete', etc.
     * @param string|null $table   The table affected, if any ('account' for
     *                             auth-lifecycle events, matching how the
     *                             reference UI groups them).
     * @param int|null    $recordId
     */
    public static function log(?int $userId, string $action, ?string $table = null, ?int $recordId = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, table_affected, record_id, ip_address, created_at)
             VALUES (:user_id, :action, :table_affected, :record_id, :ip, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'table_affected' => $table,
            'record_id' => $recordId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
