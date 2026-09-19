<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$body = ffms_body();
$email = trim(strtolower((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if (!$email || !$password) {
    Response::error('Email and password are required.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT u.user_id, u.first_name, u.last_name, u.email, u.password_hash, u.phone,
            u.status, u.email_verified_at, r.role_name
     FROM users u JOIN roles r ON r.role_id = u.role_id
     WHERE u.email = :email LIMIT 1'
);
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
    Response::error('Incorrect email or password.', 401);
}
if ($user['status'] !== 'active') {
    Response::error('This account is not active. Contact an administrator.', 403);
}
if (!$user['email_verified_at']) {
    Response::error('Please verify your email before signing in. Check your inbox, or request a new link.', 403);
}

[$rawToken, $tokenHash] = Auth::generateToken();
$config = require __DIR__ . '/../config/config.php';
$expiresAt = date('Y-m-d H:i:s', time() + $config['app']['token_ttl_days'] * 86400);

$pdo->prepare(
    'INSERT INTO auth_tokens (user_id, token_hash, user_agent, ip_address, expires_at)
     VALUES (:user_id, :hash, :ua, :ip, :expires)'
)->execute([
    'user_id' => $user['user_id'],
    'hash' => $tokenHash,
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'expires' => $expiresAt,
]);

AuditLogger::log((int)$user['user_id'], 'login', 'account');

Response::ok([
    'token' => $rawToken,
    'user' => [
        'id' => (int) $user['user_id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role_name'],
    ],
]);
