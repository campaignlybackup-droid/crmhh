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
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Permission denied: You do not have permission to perform this action.']);
            exit;
        }
        fatal_error('You do not have permission to perform this action.');
    }

    private static function isAjax(): bool
    {
        return (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
            || str_contains($_SERVER['REQUEST_URI'] ?? '', 'api_')
            || str_contains($_SERVER['REQUEST_URI'] ?? '', 'api=');
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
            $directRows = Database::all(
                "SELECT id FROM users WHERE manager_id = ? AND deleted_at IS NULL",
                [$userId]
            );
            $ids = array_map('intval', array_merge(array_column($rows, 'user_id'), array_column($directRows, 'id')));
            $ids[] = $userId;
            self::$managedCache[$userId] = array_values(array_unique($ids));
        }
        return self::$managedCache[$userId];
    }

    /**
     * User ids of all peers in any team that $userId belongs to, plus themselves.
     */
    public static function teamUserIds(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return [];

        $rows = Database::all(
            "SELECT DISTINCT tm2.user_id FROM team_members tm1
             JOIN team_members tm2 ON tm2.team_id = tm1.team_id
             WHERE tm1.user_id = ?",
            [$userId]
        );
        $ids = array_map('intval', array_column($rows, 'user_id'));
        $ids[] = $userId;
        return array_values(array_unique($ids));
    }

    public static function isFounder(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;
        if ($userId === Auth::id() && Auth::isFounder()) return true;
        $user = Database::one('SELECT is_founder FROM users WHERE id=?', [$userId]);
        if (!empty($user) && (int)$user['is_founder'] === 1) return true;
        return in_array('founder', UserModel::roleSlugsFor($userId), true);
    }

    public static function isManager(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;
        if (self::isFounder($userId)) return false;
        if (!empty(UserModel::managedTeamsFor($userId))) return true;
        return in_array('manager', UserModel::roleSlugsFor($userId), true);
    }

    /**
     * Returns null when the user is Founder (can see everything),
     * otherwise an array of user ids whose records are visible to them:
     * for managers, themselves + managed team members; for regular members, only themselves.
     */
    public static function scopeIds(string $viewAllSlug, ?int $userId = null): ?array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return [];
        if (self::isFounder($userId)) {
            return null; // Founder sees everything
        }
        if (self::isManager($userId)) {
            return self::managedUserIds($userId);
        }
        return [$userId];
    }

    /** Whether $managerId manages $targetUserId (or is the same person). */
    public static function manages(int $managerId, int $targetUserId): bool
    {
        return in_array($targetUserId, self::managedUserIds($managerId), true);
    }

    /**
     * Builds a SQL fragment restricting clients to ones assigned to the user or their managed team.
     * ONLY the founder gets 1=1 (all clients).
     * Non-founders only see clients where they or their team have active service assignments, tasks, or content deliverables.
     */
    public static function clientVisibility(?int $userId = null, string $clientColumn = 'clients.id'): array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return ['sql' => '0=1', 'params' => []];

        if (self::isFounder($userId)) {
            return ['sql' => '1=1', 'params' => []];
        }

        $ids = self::isManager($userId) ? self::managedUserIds($userId) : [$userId];
        if (empty($ids)) {
            return ['sql' => '0=1', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "(EXISTS (
                    SELECT 1 FROM client_services cs
                    LEFT JOIN client_service_assignments csa ON csa.client_service_id = cs.id
                    WHERE cs.client_id = {$clientColumn}
                      AND cs.deleted_at IS NULL
                      AND (cs.manager_id IN ($placeholders) OR csa.user_id IN ($placeholders))
                )
                OR EXISTS (
                    SELECT 1 FROM tasks t
                    WHERE t.client_id = {$clientColumn}
                      AND t.deleted_at IS NULL
                      AND (t.assigned_user_id IN ($placeholders) OR t.assigned_by IN ($placeholders))
                )
                OR EXISTS (
                    SELECT 1 FROM content_calendar cc
                    WHERE cc.client_id = {$clientColumn}
                      AND (cc.assigned_to IN ($placeholders) OR cc.editor_checked_by IN ($placeholders))
                ))";
        return ['sql' => $sql, 'params' => array_merge($ids, $ids, $ids, $ids, $ids, $ids)];
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
