<?php
/**
 * Loads key=value pairs from .env into getenv()/$_ENV without needing a
 * Composer package. Silently does nothing if .env is missing (e.g. on a
 * host where real environment variables are set through the control panel
 * instead of a file).
 */
function ffms_load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // strip matching surrounding quotes
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

ffms_load_env(__DIR__ . '/../.env');

function ffms_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

return [
    'db' => [
        'host' => ffms_env('DB_HOST', '127.0.0.1'),
        'port' => ffms_env('DB_PORT', '3306'),
        'name' => ffms_env('DB_NAME', 'ffms_db'),
        'user' => ffms_env('DB_USER', 'root'),
        'pass' => ffms_env('DB_PASS', ''),
    ],
    'app' => [
        // Comma-separated list of origins allowed to call this API
        // (your Netlify URL in production, http://localhost:5500 in dev).
        'allowed_origins' => array_filter(array_map('trim', explode(',', ffms_env('ALLOWED_ORIGINS', 'http://localhost:5500')))),
        'frontend_url' => ffms_env('FRONTEND_URL', 'http://localhost:5500'),
        'token_ttl_days' => (int) ffms_env('TOKEN_TTL_DAYS', '30'),
        'verification_ttl_hours' => (int) ffms_env('VERIFICATION_TTL_HOURS', '48'),
        'reset_ttl_minutes' => (int) ffms_env('RESET_TTL_MINUTES', '60'),
    ],
    'mail' => [
        'host' => ffms_env('SMTP_HOST', ''),
        'port' => (int) ffms_env('SMTP_PORT', '587'),
        'user' => ffms_env('SMTP_USER', ''),
        'pass' => ffms_env('SMTP_PASS', ''),
        'from_email' => ffms_env('MAIL_FROM_EMAIL', 'no-reply@ffms.local'),
        'from_name' => ffms_env('MAIL_FROM_NAME', 'FFMS Farm Management'),
        'encryption' => ffms_env('SMTP_ENCRYPTION', 'tls'), // tls | ssl
    ],
];
