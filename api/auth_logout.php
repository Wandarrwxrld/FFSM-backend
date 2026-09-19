<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$raw = Auth::bearerToken();
if ($raw) {
    $pdo = Database::connection();
    $hash = Auth::hashToken($raw);

    $stmt = $pdo->prepare('SELECT user_id FROM auth_tokens WHERE token_hash = :hash LIMIT 1');
    $stmt->execute(['hash' => $hash]);
    $row = $stmt->fetch();

    $pdo->prepare('DELETE FROM auth_tokens WHERE token_hash = :hash')->execute(['hash' => $hash]);

    if ($row) {
        AuditLogger::log((int)$row['user_id'], 'logout', 'account');
    }
}

Response::ok(['message' => 'Signed out.']);
