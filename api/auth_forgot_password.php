<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$body = ffms_body();
$email = trim(strtolower((string)($body['email'] ?? '')));
if (!$email) {
    Response::error('Email is required.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT user_id, first_name FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

// Same email either way, so this endpoint can't be used to check which
// addresses have accounts.
$generic = ['message' => "If an account with that email exists, we've sent a password reset link."];

if (!$user) {
    Response::ok($generic);
}

[$rawToken, $tokenHash] = Auth::generateToken();
require_once __DIR__ . '/../config/config.php';
$config = ffms_config();
$expiresAt = date('Y-m-d H:i:s', time() + $config['app']['reset_ttl_minutes'] * 60);

$pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)')
    ->execute(['user_id' => $user['user_id'], 'hash' => $tokenHash, 'expires' => $expiresAt]);

Mailer::sendPasswordResetEmail($email, $user['first_name'], $rawToken);
AuditLogger::log((int)$user['user_id'], 'password reset requested', 'account');

Response::ok($generic);
