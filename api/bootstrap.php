<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak PHP errors as HTML into a JSON API

require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AuditLogger.php';

// Convert PHP errors/exceptions into a clean JSON response instead of an
// HTML stack trace, which would otherwise break every fetch() call on
// the front end.
set_exception_handler(function (Throwable $e) {
    error_log('[Unhandled] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Something went wrong on the server.', 500);
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../config/config.php';
$config = ffms_config();

// ---- CORS ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['app']['allowed_origins'], true) || in_array('*', $config['app']['allowed_origins'], true)) {
    header("Access-Control-Allow-Origin: {$origin}");
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** Reads and JSON-decodes the request body, or [] if empty/invalid. */
function ffms_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
