<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$user = Auth::requireUser();
$body = ffms_body();
$currentPassword = (string)($body['currentPassword'] ?? '');
$newPassword = (string)($body['newPassword'] ?? '');

if (!$currentPassword || !$newPassword) {
    Response::error('Current and new password are both required.');
}
if (!Auth::passwordMeetsPolicy($newPassword)) {
    Response::error(Auth::passwordPolicyMessage());
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute(['id' => $user['user_id']]);
$row = $stmt->fetch();

if (!$row || !Auth::verifyPassword($currentPassword, $row['password_hash'])) {
    Response::error('Current password is incorrect.', 401);
}

$pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id')
    ->execute(['hash' => Auth::hashPassword($newPassword), 'id' => $user['user_id']]);

AuditLogger::log((int)$user['user_id'], 'password changed', 'account');

Response::ok(['message' => 'Password updated.']);
