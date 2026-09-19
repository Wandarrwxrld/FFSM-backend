<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$body = ffms_body();
$rawToken = (string)($body['token'] ?? '');
if (!$rawToken) {
    Response::error('Missing verification token.');
}

$pdo = Database::connection();
$hash = Auth::hashToken($rawToken);

$stmt = $pdo->prepare(
    'SELECT ev.verification_id, ev.user_id, ev.expires_at, ev.verified_at, u.email_verified_at
     FROM email_verifications ev
     JOIN users u ON u.user_id = ev.user_id
     WHERE ev.token_hash = :hash
     ORDER BY ev.verification_id DESC
     LIMIT 1'
);
$stmt->execute(['hash' => $hash]);
$row = $stmt->fetch();

if (!$row) {
    Response::error('This verification link is invalid.', 400);
}
if ($row['email_verified_at']) {
    Response::ok(['message' => 'Your email is already verified. You can sign in.']);
}
if (strtotime($row['expires_at']) < time()) {
    Response::error('This verification link has expired. Request a new one.', 410);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE user_id = :id')
        ->execute(['id' => $row['user_id']]);
    $pdo->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE verification_id = :id')
        ->execute(['id' => $row['verification_id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

AuditLogger::log((int)$row['user_id'], 'email verified', 'account');

Response::ok(['message' => 'Email verified. You can now sign in.']);
