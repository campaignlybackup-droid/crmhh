<?php

class ReportModel
{
    /**
     * Non-destructive self-healing check to ensure daily_reports table and all columns exist.
     */
    public static function ensureSchema(): void
    {
        try {
            $pdo = Database::pdo();
            $tableExists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'daily_reports'")->fetchColumn();
            if ($tableExists === 0) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS daily_reports (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    report_date DATE NOT NULL,
                    work_completed TEXT DEFAULT NULL,
                    tasks_worked_on TEXT DEFAULT NULL,
                    pending_work TEXT DEFAULT NULL,
                    blockers TEXT DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_dr_user_date (user_id, report_date),
                    CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                return;
            }

            $cols = Database::getTableColumns($pdo, 'daily_reports');

            if (!in_array('work_completed', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN work_completed TEXT DEFAULT NULL");
                if (in_array('completed_tasks', $cols, true)) {
                    $pdo->exec("UPDATE daily_reports SET work_completed = completed_tasks WHERE work_completed IS NULL AND completed_tasks IS NOT NULL");
                }
            }
            if (!in_array('tasks_worked_on', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN tasks_worked_on TEXT DEFAULT NULL AFTER work_completed");
            }
            if (!in_array('pending_work', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN pending_work TEXT DEFAULT NULL AFTER tasks_worked_on");
                if (in_array('pending_tasks', $cols, true)) {
                    $pdo->exec("UPDATE daily_reports SET pending_work = pending_tasks WHERE pending_work IS NULL AND pending_tasks IS NOT NULL");
                }
            }
            if (!in_array('blockers', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN blockers TEXT DEFAULT NULL");
            }
            if (!in_array('notes', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN notes TEXT DEFAULT NULL");
            }
            if (!in_array('updated_at', $cols, true)) {
                $pdo->exec("ALTER TABLE daily_reports ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }

            $existingDrIndexes = $pdo->query("SHOW INDEX FROM `daily_reports`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $drIndexNames = array_column($existingDrIndexes, 'Key_name');
            if (!in_array('uniq_dr_user_date', $drIndexNames, true)) {
                try {
                    $pdo->exec("ALTER TABLE `daily_reports` ADD UNIQUE KEY `uniq_dr_user_date` (`user_id`, `report_date`)");
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {
            app_log('ReportModel::ensureSchema error: ' . $e->getMessage());
        }
    }

    public static function findForDate(int $userId, string $date): ?array
    {
        try {
            $row = Database::one('SELECT * FROM daily_reports WHERE user_id = ? AND DATE(report_date) = ?', [$userId, $date]);
            if ($row) {
                if (!isset($row['work_completed']) && isset($row['completed_tasks'])) {
                    $row['work_completed'] = $row['completed_tasks'];
                }
                if (!isset($row['pending_work']) && isset($row['pending_tasks'])) {
                    $row['pending_work'] = $row['pending_tasks'];
                }
            }
            return $row;
        } catch (Throwable $e) {
            self::ensureSchema();
            $row = Database::one('SELECT * FROM daily_reports WHERE user_id = ? AND DATE(report_date) = ?', [$userId, $date]);
            if ($row) {
                if (!isset($row['work_completed']) && isset($row['completed_tasks'])) {
                    $row['work_completed'] = $row['completed_tasks'];
                }
                if (!isset($row['pending_work']) && isset($row['pending_tasks'])) {
                    $row['pending_work'] = $row['pending_tasks'];
                }
            }
            return $row;
        }
    }

    public static function upsert(int $userId, string $date, array $data): void
    {
        $workCompleted = isset($data['work_completed']) && trim((string)$data['work_completed']) !== '' ? trim((string)$data['work_completed']) : null;
        $tasksWorkedOn = isset($data['tasks_worked_on']) && trim((string)$data['tasks_worked_on']) !== '' ? trim((string)$data['tasks_worked_on']) : null;
        $pendingWork   = isset($data['pending_work']) && trim((string)$data['pending_work']) !== '' ? trim((string)$data['pending_work']) : null;
        $blockers      = isset($data['blockers']) && trim((string)$data['blockers']) !== '' ? trim((string)$data['blockers']) : null;
        $notes         = isset($data['notes']) && trim((string)$data['notes']) !== '' ? trim((string)$data['notes']) : null;

        $saveReport = function() use ($userId, $date, $workCompleted, $tasksWorkedOn, $pendingWork, $blockers, $notes) {
            $existing = self::findForDate($userId, $date);
            if ($existing) {
                Database::run(
                    'UPDATE daily_reports SET work_completed=?, tasks_worked_on=?, pending_work=?, blockers=?, notes=?, updated_at=NOW() WHERE id=?',
                    [$workCompleted, $tasksWorkedOn, $pendingWork, $blockers, $notes, $existing['id']]
                );
            } else {
                try {
                    Database::run(
                        'INSERT INTO daily_reports (user_id, report_date, work_completed, tasks_worked_on, pending_work, blockers, notes, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,NOW(),NOW())
                         ON DUPLICATE KEY UPDATE
                         work_completed=VALUES(work_completed), tasks_worked_on=VALUES(tasks_worked_on), pending_work=VALUES(pending_work), blockers=VALUES(blockers), notes=VALUES(notes), updated_at=NOW()',
                        [$userId, $date, $workCompleted, $tasksWorkedOn, $pendingWork, $blockers, $notes]
                    );
                } catch (Throwable $ex) {
                    $fallbackRow = self::findForDate($userId, $date);
                    if ($fallbackRow) {
                        Database::run(
                            'UPDATE daily_reports SET work_completed=?, tasks_worked_on=?, pending_work=?, blockers=?, notes=?, updated_at=NOW() WHERE id=?',
                            [$workCompleted, $tasksWorkedOn, $pendingWork, $blockers, $notes, $fallbackRow['id']]
                        );
                    } else {
                        throw $ex;
                    }
                }
            }
        };

        try {
            $saveReport();
        } catch (Throwable $e) {
            // Schema mismatch: automatically patch schema on the fly and retry once
            self::ensureSchema();
            $saveReport();
        }

        AuditLog::record('submit_report', 'daily_report', $userId, null, $date);
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['1=1'];
        $params = [];

        $isFounder = Permission::isFounder($userId);
        if ($isFounder) {
            // no restriction
        } elseif (Permission::has('reports.view_team', $userId)) {
            $ids = array_values(array_unique(array_merge([$userId], Permission::managedUserIds($userId))));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "dr.user_id IN ($ph)";
                $params = array_merge($params, $ids);
            } else {
                $where[] = 'dr.user_id = ?';
                $params[] = $userId;
            }
        } else {
            $where[] = 'dr.user_id = ?';
            $params[] = $userId;
        }

        if (!empty($filters['user_id'])) { $where[] = 'dr.user_id = ?'; $params[] = $filters['user_id']; }
        if (!empty($filters['date_from'])) { $where[] = 'dr.report_date >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $where[] = 'dr.report_date <= ?'; $params[] = $filters['date_to']; }

        $whereSql = implode(' AND ', $where);
        try {
            $total = (int)Database::scalar("SELECT COUNT(*) FROM daily_reports dr WHERE $whereSql", $params);
            $p = paginate_params($total, $page, $perPage);
            $rows = Database::all(
                "SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id
                 WHERE $whereSql ORDER BY dr.report_date DESC, u.name LIMIT {$p['perPage']} OFFSET {$p['offset']}",
                $params
            );
        } catch (Throwable $e) {
            self::ensureSchema();
            $total = (int)Database::scalar("SELECT COUNT(*) FROM daily_reports dr WHERE $whereSql", $params);
            $p = paginate_params($total, $page, $perPage);
            $rows = Database::all(
                "SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id
                 WHERE $whereSql ORDER BY dr.report_date DESC, u.name LIMIT {$p['perPage']} OFFSET {$p['offset']}",
                $params
            );
        }

        foreach ($rows as &$r) {
            if (!isset($r['work_completed']) && isset($r['completed_tasks'])) {
                $r['work_completed'] = $r['completed_tasks'];
            }
            if (!isset($r['pending_work']) && isset($r['pending_tasks'])) {
                $r['pending_work'] = $r['pending_tasks'];
            }
        }
        unset($r);

        return [$rows, $p];
    }

    public static function getMonthlyReports(int $year, int $month, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $where = ['dr.report_date >= ?', 'dr.report_date <= ?'];
        $params = [$startDate, $endDate];

        if (Permission::has('reports.view_all', $userId)) {
            // no restriction
        } elseif (Permission::has('reports.view_team', $userId)) {
            $ids = array_values(array_unique(array_merge([$userId], Permission::managedUserIds($userId))));
            if (empty($ids)) return []; // No team to view
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "dr.user_id IN ($ph)";
            $params = array_merge($params, $ids);
        } else {
            $where[] = 'dr.user_id = ?';
            $params[] = $userId;
        }

        $whereSql = implode(' AND ', $where);
        try {
            $rows = Database::all(
                "SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id
                 WHERE $whereSql ORDER BY dr.report_date ASC, u.name ASC",
                $params
            );
        } catch (Throwable $e) {
            self::ensureSchema();
            $rows = Database::all(
                "SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id
                 WHERE $whereSql ORDER BY dr.report_date ASC, u.name ASC",
                $params
            );
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (!isset($row['work_completed']) && isset($row['completed_tasks'])) {
                $row['work_completed'] = $row['completed_tasks'];
            }
            if (!isset($row['pending_work']) && isset($row['pending_tasks'])) {
                $row['pending_work'] = $row['pending_tasks'];
            }
            $date = $row['report_date'];
            if (!isset($grouped[$date])) $grouped[$date] = [];
            $grouped[$date][] = $row;
        }
        return $grouped;
    }
}
