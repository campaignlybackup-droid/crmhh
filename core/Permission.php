<?php
/**
 * Central permission + data-visibility engine.
 *
 * Every sensitive query in the application MUST run its results through
 * this class rather than trusting role names or client-side state.
 * Visibility = ROLE + PERMISSION + HIERARCHY + ASSIGNMENT.
 */

class Permission
{
    /** @var array<int, string[]> cache of permission slugs per user id */
    private static array $permCache = [];

    /** @var array<int, int[]> cache of managed user ids per user id */
    private static array $managedCache = [];

    public static function slugsFor(int $userId): array
    {
        if (!isset(self::$permCache[$userId])) {
            $rows = Database::all(
                'SELECT DISTINCT p.slug FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN user_roles ur ON ur.role_id = rp.role_id
                 WHERE ur.user_id = ?',
                [$userId]
            );
            self::$permCache[$userId] = array_column($rows, 'slug');
        }
        return self::$permCache[$userId];
    }

    public static function has(string $slug, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;

        $user = $userId === Auth::id() ? Auth::user() : Database::one('SELECT * FROM users WHERE id=?', [$userId]);
        if ($user && (int)$user['is_founder'] === 1) {
            return true; // Founder flag lives in the DB and always carries full access.
        }

        return in_array($slug, self::slugsFor($userId), true);
    }

    public static function hasAny(array $slugs, ?int $userId = null): bool
    {
        foreach ($slugs as $s) {
            if (self::has($s, $userId)) return true;
        }
        return false;
    }

    /** Halts the request with 403 if the current user lacks the permission. */
    public static function require(string $slug): void
    {
        if (!self::has($slug)) {
            self::deny();
        }
    }

    public static function requireAny(array $slugs): void
    {
        if (!self::hasAny($slugs)) {
            self::deny();
        }
    }

    public static function deny(): void
    {
        http_response_code(403);
        AuditLog::record('access_denied', 'permission', null, null, $_SERVER['REQUEST_URI'] ?? null);
        if (self::isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        fatal_error('You do not have permission to perform this action.');
    }

    private static function isAjax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }

    /**
     * User ids a manager is directly responsible for: themselves plus every
     * member of every team they manage (team_managers table). Flat hierarchy
     * (Founder -> Manager -> Team Member), no manager-of-manager chains.
     */
    public static function managedUserIds(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return [];

        if (!isset(self::$managedCache[$userId])) {
            $rows = Database::all(
                "SELECT DISTINCT tm.user_id FROM team_managers tmg
                 JOIN team_members tm ON tm.team_id = tmg.team_id
                 WHERE tmg.user_id = ?",
                [$userId]
            );
            $ids = array_map('intval', array_column($rows, 'user_id'));
            $ids[] = $userId;
            self::$managedCache[$userId] = array_values(array_unique($ids));
        }
        return self::$managedCache[$userId];
    }

    /**
     * Returns null when the user can see everything (holds $viewAllSlug),
     * otherwise an array of user ids whose records are visible to them
     * (self + anyone they manage).
     */
    public static function scopeIds(string $viewAllSlug, ?int $userId = null): ?array
    {
        $userId = $userId ?? Auth::id();
        if (self::has($viewAllSlug, $userId)) {
            return null;
        }
        return self::managedUserIds($userId);
    }

    /** Whether $managerId manages $targetUserId (or is the same person). */
    public static function manages(int $managerId, int $targetUserId): bool
    {
        return in_array($targetUserId, self::managedUserIds($managerId), true);
    }

    /**
     * Builds a SQL fragment (with params) restricting client rows to ones
     * the given user is allowed to see, for use inside a WHERE clause.
     * Returns ['sql' => '(1=1)' or 'EXISTS(...)', 'params' => []].
     */
    public static function clientVisibility(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (self::has('clients.view_all', $userId)) {
            return ['sql' => '1=1', 'params' => []];
        }
        $ids = self::managedUserIds($userId);
        if (empty($ids)) {
            return ['sql' => '0=1', 'params' => []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "EXISTS (
                    SELECT 1 FROM client_services cs
                    LEFT JOIN client_service_assignments csa ON csa.client_service_id = cs.id
                    WHERE cs.client_id = clients.id
                      AND cs.deleted_at IS NULL
                      AND (cs.manager_id IN ($placeholders) OR csa.user_id IN ($placeholders))
                )
                OR clients.created_by IN ($placeholders)";
        return ['sql' => $sql, 'params' => array_merge($ids, $ids, $ids)];
    }

    public static function canAccessClient(int $clientId, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $vis = self::clientVisibility($userId);
        $row = Database::one(
            "SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL AND ({$vis['sql']})",
            array_merge([$clientId], $vis['params'])
        );
        return (bool)$row;
    }
}
