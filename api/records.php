<?php
require_once __DIR__ . '/bootstrap.php';

$schema = require __DIR__ . '/../src/Schema.php';

$entity = $_GET['entity'] ?? '';
if (!isset($schema[$entity])) {
    Response::error('Unknown entity.', 404);
}
$def = $schema[$entity];
$pk = $def['pk'];

// Server-side role enforcement — the real security boundary. The front
// end's sidebar filtering is just UX; this is what actually protects data.
//
// Reads (GET) are allowed for any logged-in user, regardless of role —
// this is what lets e.g. a Worker viewing "Machinery Usage" see which
// Field it happened in, even though "Fields" itself is an Owner/Manager/
// Agronomist-only module. Writes (POST/PUT/DELETE) still require the
// entity's own permitted roles — that's where the real protection is.
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $user = Auth::requireUser();
} else {
    $user = Auth::requireRole($def['roles']);
}
$userId = (int) $user['user_id'];

$pdo = Database::connection();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id !== null) {
            $stmt = $pdo->prepare("SELECT *, `{$pk}` AS id FROM `{$entity}` WHERE `{$pk}` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) Response::error('Not found.', 404);
            Response::ok($row);
        }

        $limit = min((int) ($_GET['limit'] ?? 500), 1000);
        $stmt = $pdo->query("SELECT *, `{$pk}` AS id FROM `{$entity}` ORDER BY `{$pk}` DESC LIMIT {$limit}");
        Response::ok($stmt->fetchAll());
        break;

    case 'POST':
        $body = ffms_body();
        [$cols, $params] = ffms_filter_columns($body, $def['columns']);

        // Farm ownership can never be spoofed by the client — always the
        // authenticated user who's creating it.
        if ($entity === 'farms') {
            $cols[] = 'owner_id';
            $params['owner_id'] = $userId;
        }

        foreach ($def['columns'] as $required) {
            // no hard NOT NULL enforcement here beyond what MySQL itself
            // enforces — the DB is still the final authority on required fields
        }

        if (empty($cols)) Response::error('No valid fields supplied.');

        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $placeholderList = implode(', ', array_map(fn($c) => ":{$c}", $cols));
        $stmt = $pdo->prepare("INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholderList})");

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            Response::error(ffms_friendly_db_error($e), 400);
        }

        $newId = (int) $pdo->lastInsertId();
        AuditLogger::log($userId, 'create', $entity, $newId);

        $fetch = $pdo->prepare("SELECT *, `{$pk}` AS id FROM `{$entity}` WHERE `{$pk}` = :id LIMIT 1");
        $fetch->execute(['id' => $newId]);
        Response::ok($fetch->fetch(), 201);
        break;

    case 'PUT':
        if ($id === null) Response::error('Missing id.');
        $body = ffms_body();
        [$cols, $params] = ffms_filter_columns($body, $def['columns']);
        if (empty($cols)) Response::error('No valid fields supplied.');

        $setList = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", $cols));
        $params['__id'] = $id;
        $stmt = $pdo->prepare("UPDATE `{$entity}` SET {$setList} WHERE `{$pk}` = :__id");

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            Response::error(ffms_friendly_db_error($e), 400);
        }

        AuditLogger::log($userId, 'update', $entity, $id);

        $fetch = $pdo->prepare("SELECT *, `{$pk}` AS id FROM `{$entity}` WHERE `{$pk}` = :id LIMIT 1");
        $fetch->execute(['id' => $id]);
        $row = $fetch->fetch();
        if (!$row) Response::error('Not found.', 404);
        Response::ok($row);
        break;

    case 'DELETE':
        if ($id === null) Response::error('Missing id.');
        $stmt = $pdo->prepare("DELETE FROM `{$entity}` WHERE `{$pk}` = :id");
        try {
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            Response::error(ffms_friendly_db_error($e), 400);
        }
        if ($stmt->rowCount() === 0) Response::error('Not found.', 404);

        AuditLogger::log($userId, 'delete', $entity, $id);
        Response::ok(['deleted' => true, 'id' => $id]);
        break;

    default:
        Response::error('Method not allowed.', 405);
}

/**
 * Keeps only keys present in the whitelist, so a request can never write
 * to a column that doesn't exist on this entity (or, worse, the primary
 * key / another table entirely).
 * @return array{0: string[], 1: array<string, mixed>}
 */
function ffms_filter_columns(array $body, array $whitelist): array
{
    $cols = [];
    $params = [];
    foreach ($whitelist as $col) {
        if (array_key_exists($col, $body)) {
            $value = $body[$col];
            $cols[] = $col;
            $params[$col] = $value === '' ? null : $value;
        }
    }
    return [$cols, $params];
}

function ffms_friendly_db_error(PDOException $e): string
{
    $msg = $e->getMessage();
    if (str_contains($msg, 'foreign key constraint fails')) {
        return 'That record is referenced by other data, or references something that doesn\'t exist.';
    }
    if (str_contains($msg, 'Duplicate entry')) {
        return 'That value already exists and must be unique.';
    }
    if (str_contains($msg, "doesn't have a default value") || str_contains($msg, 'cannot be null')) {
        return 'A required field is missing.';
    }
    return 'That request could not be saved.';
}
