<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class Auth
{
    /** Generates a random 64-char hex token and returns [rawToken, sha256Hash]. */
    public static function generateToken(): array
    {
        $raw = bin2hex(random_bytes(32));
        return [$raw, hash('sha256', $raw)];
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Same rule set the front end's strength meter enforces: at least 8
     * characters, one uppercase, one lowercase, one number, one symbol.
     * Enforced again here because client-side validation is only a UX
     * nicety — the server can never trust it.
     */
    public static function passwordMeetsPolicy(string $password): bool
    {
        if (strlen($password) < 8) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) return false;
        return true;
    }

    public static function passwordPolicyMessage(): string
    {
        return 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.';
    }

    /** Reads the Authorization: Bearer <token> header, or null if absent. */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }
        return substr($header, 7);
    }

    /**
     * Resolves the bearer token to a user row (joined with role_name).
     * Returns null if missing/invalid/expired — callers decide whether
     * that's acceptable (some endpoints allow anonymous access).
     */
    public static function currentUser(): ?array
    {
        $raw = self::bearerToken();
        if (!$raw) return null;

        $hash = self::hashToken($raw);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.role_id, u.first_name, u.last_name, u.email,
                    u.phone, u.status, u.email_verified_at, r.role_name,
                    t.token_id, t.expires_at
             FROM auth_tokens t
             JOIN users u ON u.user_id = t.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE t.token_hash = :hash
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) return null;
        if (strtotime($row['expires_at']) < time()) return null;

        // Sliding activity timestamp, used by the audit trail and to
        // let genuinely stale tokens be cleaned up later.
        $pdo->prepare('UPDATE auth_tokens SET last_used_at = NOW() WHERE token_id = :id')
            ->execute(['id' => $row['token_id']]);

        return $row;
    }

    /** Requires a valid, logged-in user or halts the request with 401. */
    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) {
            Response::error('Not authenticated. Please sign in again.', 401);
        }
        return $user;
    }

    /** Requires the current user's role to be in $roles, or halts with 403. */
    public static function requireRole(array $roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role_name'], $roles, true)) {
            Response::error('You do not have permission to do that.', 403);
        }
        return $user;
    }
}
