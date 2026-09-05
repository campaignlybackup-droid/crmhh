<?php

class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): array
    {
        $email = trim(strtolower($email));

        // Basic brute-force throttling per session
        $_SESSION['_login_attempts'] = $_SESSION['_login_attempts'] ?? ['count' => 0, 'first' => time()];
        if (time() - $_SESSION['_login_attempts']['first'] > 900) {
            $_SESSION['_login_attempts'] = ['count' => 0, 'first' => time()];
        }
        if ($_SESSION['_login_attempts']['count'] >= 10) {
            return [false, 'Too many login attempts. Please try again in a few minutes.'];
        }

        $user = Database::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['_login_attempts']['count']++;
            return [false, 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return [false, 'This account has been disabled. Please contact your administrator.'];
        }

        unset($_SESSION['_login_attempts']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        AuditLog::record('login', 'user', $user['id']);

        return [true, null];
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLog::record('logout', 'user', self::id());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        if (self::$user === null) {
            self::$user = Database::one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [self::id()]);
            if (!self::$user || self::$user['status'] !== 'active') {
                self::logout();
                self::$user = null;
                return null;
            }
        }
        return self::$user;
    }

    public static function isFounder(): bool
    {
        $u = self::user();
        return $u && (int)$u['is_founder'] === 1;
    }

    public static function roles(): array
    {
        $u = self::user();
        if (!$u) return [];
        static $cache = [];
        if (!isset($cache[$u['id']])) {
            $cache[$u['id']] = Database::all(
                'SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
                [$u['id']]
            );
        }
        return $cache[$u['id']];
    }

    public static function hasRole(string $slug): bool
    {
        foreach (self::roles() as $r) {
            if ($r['slug'] === $slug) return true;
        }
        return false;
    }

    public static function requireLogin(): void
    {
        if (!self::check() || !self::user()) {
            redirect(url('login'));
        }
    }
}
