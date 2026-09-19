<?php
require_once __DIR__ . '/bootstrap.php';

Auth::requireRole(['admin', 'owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed.', 405);
}

$pdo = Database::connection();
$limit = min((int) ($_GET['limit'] ?? 200), 1000);

$stmt = $pdo->query(
    "SELECT al.log_id AS id, al.action, al.table_affected AS tableAffected,
            al.record_id AS recordId, al.ip_address AS ipAddress, al.created_at AS createdAt,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), u.email, 'Unknown') AS actor
     FROM audit_logs al
     LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT {$limit}"
);
Response::ok($stmt->fetchAll());
