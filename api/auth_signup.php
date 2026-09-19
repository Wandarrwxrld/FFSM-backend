<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

$body = ffms_body();
$firstName = trim((string)($body['firstName'] ?? ''));
$lastName  = trim((string)($body['lastName'] ?? ''));
$email     = trim(strtolower((string)($body['email'] ?? '')));
$phone     = trim((string)($body['phone'] ?? ''));
$password  = (string)($body['password'] ?? '');
$roleName  = trim((string)($body['role'] ?? ''));

if (!$firstName || !$lastName || !$email || !$password || !$roleName) {
    Response::error('First name, last name, email, password and role are all required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Enter a valid email address.');
}
if (!Auth::passwordMeetsPolicy($password)) {
    Response::error(Auth::passwordPolicyMessage());
}

$pdo = Database::connection();

$roleStmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
$roleStmt->execute(['name' => $roleName]);
$role = $roleStmt->fetch();
if (!$role) {
    Response::error('That role is not recognised.');
}

$existing = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
$existing->execute(['email' => $email]);
if ($existing->fetch()) {
    Response::error('An account with that email already exists.', 409);
}

$pdo->beginTransaction();
try {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, status, created_at, updated_at)
         VALUES (:role_id, :first_name, :last_name, :email, :password_hash, :phone, :status, NOW(), NOW())'
    );
    $insertUser->execute([
        'role_id' => $role['role_id'],
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => Auth::hashPassword($password),
        'phone' => $phone ?: null,
        'status' => 'active',
    ]);
    $userId = (int) $pdo->lastInsertId();

    [$rawToken, $tokenHash] = Auth::generateToken();
    $config = require __DIR__ . '/../config/config.php';
    $expiresAt = date('Y-m-d H:i:s', time() + $config['app']['verification_ttl_hours'] * 3600);

    $insertVerification = $pdo->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)'
    );
    $insertVerification->execute(['user_id' => $userId, 'hash' => $tokenHash, 'expires' => $expiresAt]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

AuditLogger::log($userId, 'signup', 'account');
$emailSent = Mailer::sendVerificationEmail($email, $firstName, $rawToken);

Response::ok([
    'message' => 'Account created. Check your email for a verification link.',
    'emailSent' => $emailSent,
], 201);
