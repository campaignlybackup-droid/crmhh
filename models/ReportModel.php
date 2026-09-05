<?php

class ReportModel
{
    public static function findForDate(int $userId, string $date): ?array
    {
        return Database::one('SELECT * FROM daily_reports WHERE user_id = ? AND report_date = ?', [$userId, $date]);
    }

    public static function upsert(int $userId, string $date, array $data): void
    {
        $existing = self::findForDate($userId, $date);
        if ($existing) {
            Database::run(
                'UPDATE daily_reports SET work_completed=?, tasks_worked_on=?, pending_work=?, blockers=?, notes=? WHERE id=?',
                [$data['work_completed'] ?: null, $data['tasks_worked_on'] ?: null, $data['pending_work'] ?: null, $data['blockers'] ?: null, $data['notes'] ?: null, $existing['id']]
            );
        } else {
            Database::run(
                'INSERT INTO daily_reports (user_id, report_date, work_completed, tasks_worked_on, pending_work, blockers, notes, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())',
                [$userId, $date, $data['work_completed'] ?: null, $data['tasks_worked_on'] ?: null, $data['pending_work'] ?: null, $data['blockers'] ?: null, $data['notes'] ?: null]
            );
        }
        AuditLog::record('submit_report', 'daily_report', $userId, null, $date);
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['1=1'];
        $params = [];

        if (Permission::has('reports.view_all', $userId)) {
            // no restriction
        } elseif (Permission::has('reports.view_team', $userId)) {
            $ids = Permission::managedUserIds($userId);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "dr.user_id IN ($ph)";
            $params = array_merge($params, $ids);
        } else {
            $where[] = 'dr.user_id = ?';
            $params[] = $userId;
        }

        if (!empty($filters['user_id'])) { $where[] = 'dr.user_id = ?'; $params[] = $filters['user_id']; }
        if (!empty($filters['date_from'])) { $where[] = 'dr.report_date >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $where[] = 'dr.report_date <= ?'; $params[] = $filters['date_to']; }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM daily_reports dr WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id
             WHERE $whereSql ORDER BY dr.report_date DESC, u.name LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }
}
