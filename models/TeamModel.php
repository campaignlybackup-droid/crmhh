<?php

class TeamModel
{
    public static function all(): array
    {
        return Database::all('SELECT * FROM teams WHERE deleted_at IS NULL ORDER BY name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM teams WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function members(int $teamId): array
    {
        return Database::all(
            'SELECT u.* FROM users u JOIN team_members tm ON tm.user_id = u.id WHERE tm.team_id = ? AND u.deleted_at IS NULL ORDER BY u.name',
            [$teamId]
        );
    }

    public static function managers(int $teamId): array
    {
        return Database::all(
            'SELECT u.* FROM users u JOIN team_managers tmg ON tmg.user_id = u.id WHERE tmg.team_id = ? AND u.deleted_at IS NULL ORDER BY u.name',
            [$teamId]
        );
    }

    public static function create(string $name, ?string $description, array $memberIds, array $managerIds): int
    {
        Database::beginTransaction();
        try {
            Database::run('INSERT INTO teams (name, description, created_by, created_at) VALUES (?,?,?,NOW())', [$name, $description, Auth::id()]);
            $id = (int)Database::lastInsertId();
            self::syncMembers($id, $memberIds);
            self::syncManagers($id, $managerIds);
            AuditLog::record('create', 'team', $id, null, $name);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function update(int $id, string $name, ?string $description, array $memberIds, array $managerIds): void
    {
        Database::beginTransaction();
        try {
            Database::run('UPDATE teams SET name=?, description=? WHERE id=?', [$name, $description, $id]);
            self::syncMembers($id, $memberIds);
            self::syncManagers($id, $managerIds);
            AuditLog::record('update', 'team', $id, null, $name);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function syncMembers(int $teamId, array $memberIds): void
    {
        Database::run('DELETE FROM team_members WHERE team_id = ?', [$teamId]);
        foreach (array_unique($memberIds) as $uid) {
            Database::run('INSERT IGNORE INTO team_members (team_id, user_id, added_at) VALUES (?,?,NOW())', [$teamId, (int)$uid]);
        }
    }

    private static function syncManagers(int $teamId, array $managerIds): void
    {
        Database::run('DELETE FROM team_managers WHERE team_id = ?', [$teamId]);
        foreach (array_unique($managerIds) as $uid) {
            Database::run('INSERT IGNORE INTO team_managers (team_id, user_id, can_approve_leave) VALUES (?,?,1)', [$teamId, (int)$uid]);
        }
    }

    public static function softDelete(int $id): void
    {
        $team = self::find($id);
        Database::run('UPDATE teams SET deleted_at = NOW() WHERE id = ?', [$id]);
        AuditLog::record('delete', 'team', $id, $team['name'] ?? null);
    }

    public static function workload(int $teamId): array
    {
        return Database::all(
            "SELECT u.id, u.name,
                    SUM(CASE WHEN t.status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS open_tasks,
                    SUM(CASE WHEN t.status NOT IN ('completed','cancelled') AND t.deadline < NOW() THEN 1 ELSE 0 END) AS overdue_tasks,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks
             FROM users u
             JOIN team_members tm ON tm.user_id = u.id
             LEFT JOIN tasks t ON t.assigned_user_id = u.id AND t.deleted_at IS NULL
             WHERE tm.team_id = ? AND u.deleted_at IS NULL
             GROUP BY u.id, u.name ORDER BY u.name",
            [$teamId]
        );
    }
}
