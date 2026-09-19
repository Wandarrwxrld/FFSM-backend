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
$stmt = $pdo->prepare('SELECT user_id, first_name, email_verified_at FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

// Always return the same generic message whether or not the account
// exists — this prevents the endpoint being used to enumerate registered
// email addresses.
$generic = ['message' => "If an account with that email exists and isn't verified yet, we've sent a new link."];

if (!$user || $user['email_verified_at']) {
    Response::ok($generic);
}

[$rawToken, $tokenHash] = Auth::generateToken();
$config = require __DIR__ . '/../config/config.php';
$expiresAt = date('Y-m-d H:i:s', time() + $config['app']['verification_ttl_hours'] * 3600);

$pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)')
    ->execute(['user_id' => $user['user_id'], 'hash' => $tokenHash, 'expires' => $expiresAt]);

Mailer::sendVerificationEmail($email, $user['first_name'], $rawToken);
AuditLogger::log((int)$user['user_id'], 'verification resent', 'account');

Response::ok($generic);
