<?php
require_once __DIR__ . '/bootstrap.php';

$currentUser = Auth::requireUser(); // any logged-in user may view the list
$pdo = Database::connection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT u.user_id AS id, u.first_name AS firstName, u.last_name AS lastName,
                u.email, u.phone, u.status, u.email_verified_at AS emailVerifiedAt,
                u.created_at AS createdAt, r.role_name AS role
         FROM users u JOIN roles r ON r.role_id = u.role_id
         ORDER BY u.created_at DESC'
    );
    Response::ok($stmt->fetchAll());
}

if ($method === 'PUT') {
    // Only admins/owners may change someone else's role or status.
    Auth::requireRole(['admin', 'owner']);

    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if (!$id) Response::error('Missing id.');
    if ($id === (int) $currentUser['user_id']) {
        Response::error("You can't change your own role or status here.");
    }

    $body = ffms_body();
    $updates = [];
    $params = ['id' => $id];

    if (isset($body['role'])) {
        $roleStmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
        $roleStmt->execute(['name' => $body['role']]);
        $role = $roleStmt->fetch();
        if (!$role) Response::error('Unknown role.');
        $updates[] = 'role_id = :role_id';
        $params['role_id'] = $role['role_id'];
    }
    if (isset($body['status']) && in_array($body['status'], ['active', 'inactive', 'suspended'], true)) {
        $updates[] = 'status = :status';
        $params['status'] = $body['status'];
    }
    if (empty($updates)) Response::error('Nothing to update.');

    $updates[] = 'updated_at = NOW()';
    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = :id';
    $pdo->prepare($sql)->execute($params);

    AuditLogger::log((int) $currentUser['user_id'], 'staff changed', 'team', $id);
    Response::ok(['message' => 'Updated.']);
}

Response::error('Method not allowed.', 405);
