<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed.', 405);
}

$user = Auth::requireUser();

Response::ok([
    'id' => (int) $user['user_id'],
    'firstName' => $user['first_name'],
    'lastName' => $user['last_name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'role' => $user['role_name'],
    'status' => $user['status'],
]);
