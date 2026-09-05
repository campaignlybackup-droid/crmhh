<?php

class LeaveModel
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT lr.*, u.name AS user_name FROM leave_requests lr JOIN users u ON u.id = lr.user_id WHERE lr.id = ?', [$id]);
    }

    public static function canAccess(int $id, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $lr = Database::one('SELECT user_id FROM leave_requests WHERE id = ?', [$id]);
        if (!$lr) return false;
        if ((int)$lr['user_id'] === $userId) return true;
        if (Permission::has('leave.approve_all', $userId)) return true;
        if (Permission::has('leave.approve_team', $userId) && Permission::manages($userId, (int)$lr['user_id'])) return true;
        return false;
    }

    public static function apply(int $userId, string $start, string $end, string $reason): int
    {
        Database::run(
            'INSERT INTO leave_requests (user_id, start_date, end_date, reason, status, created_at) VALUES (?,?,?,?,"pending",NOW())',
            [$userId, $start, $end, $reason]
        );
        $id = (int)Database::lastInsertId();
        AuditLog::record('apply', 'leave', $id);

        // notify approvers: founder(s) + managers of this user's teams
        $founders = Database::all('SELECT id FROM users WHERE is_founder = 1 AND deleted_at IS NULL');
        foreach ($founders as $f) {
            Notifier::send((int)$f['id'], 'leave_request', 'New leave request', 'A team member has applied for leave.', 'leave', $id);
        }
        $managers = Database::all(
            'SELECT DISTINCT tmg.user_id FROM team_managers tmg JOIN team_members tm ON tm.team_id = tmg.team_id WHERE tm.user_id = ?',
            [$userId]
        );
        foreach ($managers as $m) {
            Notifier::send((int)$m['user_id'], 'leave_request', 'New leave request', 'A team member has applied for leave.', 'leave', $id);
        }
        return $id;
    }

    public static function decide(int $id, string $status, ?string $note): void
    {
        Database::run(
            'UPDATE leave_requests SET status=?, decided_by=?, decided_at=NOW(), decision_note=? WHERE id=?',
            [$status, Auth::id(), $note ?: null, $id]
        );
        $lr = self::find($id);
        AuditLog::record('leave_decision', 'leave', $id, 'pending', $status);
        if ($lr) {
            Notifier::send((int)$lr['user_id'], 'leave_decision', 'Leave request ' . $status, $note ?: null, 'leave', $id);
        }
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['1=1'];
        $params = [];

        if (Permission::has('leave.approve_all', $userId)) {
            // all
        } elseif (Permission::has('leave.approve_team', $userId)) {
            $ids = Permission::managedUserIds($userId);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "lr.user_id IN ($ph)";
            $params = array_merge($params, $ids);
        } else {
            $where[] = 'lr.user_id = ?';
            $params[] = $userId;
        }

        if (!empty($filters['status'])) { $where[] = 'lr.status = ?'; $params[] = $filters['status']; }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM leave_requests lr WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT lr.*, u.name AS user_name FROM leave_requests lr JOIN users u ON u.id = lr.user_id
             WHERE $whereSql ORDER BY lr.created_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }
}
