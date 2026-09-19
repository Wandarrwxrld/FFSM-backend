<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$body = ffms_body();
$rawToken = (string)($body['token'] ?? '');
$newPassword = (string)($body['password'] ?? '');

if (!$rawToken || !$newPassword) {
    Response::error('Missing token or new password.');
}
if (!Auth::passwordMeetsPolicy($newPassword)) {
    Response::error(Auth::passwordPolicyMessage());
}

$pdo = Database::connection();
$hash = Auth::hashToken($rawToken);

$stmt = $pdo->prepare(
    'SELECT reset_id, user_id, expires_at, used_at FROM password_resets
     WHERE token_hash = :hash ORDER BY reset_id DESC LIMIT 1'
);
$stmt->execute(['hash' => $hash]);
$row = $stmt->fetch();

if (!$row) {
    Response::error('This reset link is invalid.', 400);
}
if ($row['used_at']) {
    Response::error('This reset link has already been used.', 410);
}
if (strtotime($row['expires_at']) < time()) {
    Response::error('This reset link has expired. Request a new one.', 410);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id')
        ->execute(['hash' => Auth::hashPassword($newPassword), 'id' => $row['user_id']]);
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE reset_id = :id')
        ->execute(['id' => $row['reset_id']]);
    // Revoking every existing session forces re-login everywhere with the
    // new password — standard practice after a password change.
    $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = :id')
        ->execute(['id' => $row['user_id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

AuditLogger::log((int)$row['user_id'], 'password recovered', 'account');

Response::ok(['message' => 'Password updated. You can now sign in.']);
