<?php

class TaskModel
{
    const STATUSES = ['not_started', 'in_progress', 'pending_review', 'completed', 'blocked', 'cancelled'];

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT t.*, c.name AS client_name, s.name AS service_name, u.name AS assigned_name, ab.name AS assigned_by_name
             FROM tasks t
             LEFT JOIN clients c ON c.id = t.client_id
             LEFT JOIN services s ON s.id = t.service_id
             LEFT JOIN users u ON u.id = t.assigned_user_id
             LEFT JOIN users ab ON ab.id = t.assigned_by
             WHERE t.id = ? AND t.deleted_at IS NULL',
            [$id]
        );
    }

    public static function canAccess(int $taskId, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (Permission::has('tasks.view_all', $userId)) return true;
        $task = Database::one('SELECT assigned_user_id, assigned_by, is_private FROM tasks WHERE id = ? AND deleted_at IS NULL', [$taskId]);
        if (!$task) return false;
        $ids = Permission::managedUserIds($userId);
        if ((int)$task['is_private'] === 1) {
            return in_array((int)$task['assigned_user_id'], [$userId], true) || (int)$task['assigned_by'] === $userId;
        }
        return in_array((int)$task['assigned_user_id'], $ids, true) || (int)$task['assigned_by'] === $userId;
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        $scope = Permission::scopeIds('tasks.view_all', $userId);
        if ($scope !== null) {
            if (empty($scope)) {
                $where[] = '0=1';
            } else {
                $ph = implode(',', array_fill(0, count($scope), '?'));
                $where[] = "((t.assigned_user_id IN ($ph) OR t.assigned_by = ?) AND (t.is_private = 0 OR t.assigned_user_id = ? OR t.assigned_by = ?))";
                $params = array_merge($params, $scope, [$userId, $userId, $userId]);
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'overdue') {
                $where[] = "t.deadline < NOW() AND t.status NOT IN ('completed','cancelled')";
            } else {
                $where[] = 't.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['assigned_user_id'])) { $where[] = 't.assigned_user_id = ?'; $params[] = $filters['assigned_user_id']; }
        if (!empty($filters['client_id'])) { $where[] = 't.client_id = ?'; $params[] = $filters['client_id']; }
        if (!empty($filters['priority'])) { $where[] = 't.priority = ?'; $params[] = $filters['priority']; }
        if (!empty($filters['service_id'])) { $where[] = 't.service_id = ?'; $params[] = $filters['service_id']; }
        if (!empty($filters['search'])) {
            $where[] = '(t.title LIKE ? OR t.task_code LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM tasks t WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT t.*, c.name AS client_name, u.name AS assigned_name
             FROM tasks t
             LEFT JOIN clients c ON c.id = t.client_id
             LEFT JOIN users u ON u.id = t.assigned_user_id
             WHERE $whereSql ORDER BY (t.deadline IS NULL), t.deadline ASC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }

    public static function create(array $data): int
    {
        $code = next_code('tasks', 'task_code', 'TSK');
        Database::run(
            'INSERT INTO tasks (task_code, title, description, client_id, client_service_id, service_id, assigned_user_id, assigned_by, priority, status, start_date, deadline, is_private, notes, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $code, $data['title'], $data['description'] ?: null, $data['client_id'] ?: null, $data['client_service_id'] ?: null,
                $data['service_id'] ?: null, $data['assigned_user_id'] ?: null, Auth::id(), $data['priority'] ?: 'medium',
                'not_started', $data['start_date'] ?: null, $data['deadline'] ?: null, !empty($data['is_private']) ? 1 : 0, $data['notes'] ?: null,
            ]
        );
        $id = (int)Database::lastInsertId();
        ActivityModel::log('task', $id, 'created', 'Task created');
        if (!empty($data['assigned_user_id'])) {
            Database::run('INSERT INTO task_assignments (task_id, from_user_id, to_user_id, assigned_by, note, created_at) VALUES (?,?,?,?,?,NOW())',
                [$id, null, $data['assigned_user_id'], Auth::id(), 'Initial assignment']);
            Notifier::send((int)$data['assigned_user_id'], 'task_assigned', 'New task: ' . $data['title'], "Task $code has been assigned to you.", 'task', $id);
        }
        AuditLog::record('create', 'task', $id, null, $code);
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE tasks SET title=?, description=?, client_id=?, client_service_id=?, service_id=?, priority=?, start_date=?, deadline=?, notes=? WHERE id=?',
            [
                $data['title'], $data['description'] ?: null, $data['client_id'] ?: null, $data['client_service_id'] ?: null,
                $data['service_id'] ?: null, $data['priority'] ?: 'medium', $data['start_date'] ?: null, $data['deadline'] ?: null,
                $data['notes'] ?: null, $id,
            ]
        );
        ActivityModel::log('task', $id, 'updated', 'Task details updated');
        AuditLog::record('update', 'task', $id);
    }

    public static function changeStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) return;
        $before = Database::one('SELECT status FROM tasks WHERE id = ?', [$id]);
        $completedAt = $status === 'completed' ? 'NOW()' : 'NULL';
        Database::run("UPDATE tasks SET status = ?, completed_at = $completedAt WHERE id = ?", [$status, $id]);
        ActivityModel::log('task', $id, 'status_changed', null, humanize($before['status']), humanize($status));
        AuditLog::record('status_change', 'task', $id, $before['status'], $status);
    }

    public static function reassign(int $id, int $newUserId, ?string $note = null): void
    {
        $before = Database::one('SELECT assigned_user_id, title, task_code FROM tasks WHERE id = ?', [$id]);
        Database::run('UPDATE tasks SET assigned_user_id = ? WHERE id = ?', [$newUserId, $id]);
        Database::run('INSERT INTO task_assignments (task_id, from_user_id, to_user_id, assigned_by, note, created_at) VALUES (?,?,?,?,?,NOW())',
            [$id, $before['assigned_user_id'] ?: null, $newUserId, Auth::id(), $note]);
        $oldName = $before['assigned_user_id'] ? Database::scalar('SELECT name FROM users WHERE id=?', [$before['assigned_user_id']]) : 'Unassigned';
        $newName = Database::scalar('SELECT name FROM users WHERE id=?', [$newUserId]);
        ActivityModel::log('task', $id, 'reassigned', $note, $oldName, $newName);
        Notifier::send($newUserId, 'task_assigned', 'Task assigned: ' . $before['title'], "Task {$before['task_code']} has been assigned to you.", 'task', $id);
        AuditLog::record('reassign', 'task', $id, $oldName, $newName);
    }

    public static function softDelete(int $id): void
    {
        Database::run('UPDATE tasks SET deleted_at = NOW() WHERE id = ?', [$id]);
        AuditLog::record('delete', 'task', $id);
    }

    public static function overdueList(?int $userId = null, int $limit = 50): array
    {
        $userId = $userId ?? Auth::id();
        $where = ["t.deadline < NOW()", "t.status NOT IN ('completed','cancelled')", 't.deleted_at IS NULL'];
        $params = [];
        $scope = Permission::scopeIds('tasks.view_all', $userId);
        if ($scope !== null) {
            if (empty($scope)) return [];
            $ph = implode(',', array_fill(0, count($scope), '?'));
            $where[] = "t.assigned_user_id IN ($ph)";
            $params = $scope;
        }
        $whereSql = implode(' AND ', $where);
        return Database::all(
            "SELECT t.*, c.name AS client_name, u.name AS assigned_name,
                    DATEDIFF(NOW(), t.deadline) AS days_overdue
             FROM tasks t LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN users u ON u.id = t.assigned_user_id
             WHERE $whereSql ORDER BY t.deadline ASC LIMIT $limit",
            $params
        );
    }

    public static function dashboardCounts(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $scope = Permission::scopeIds('tasks.view_all', $userId);
        $where = ["deleted_at IS NULL"];
        $params = [];
        if ($scope !== null) {
            if (empty($scope)) return ['pending' => 0, 'completed' => 0, 'overdue' => 0, 'upcoming' => 0];
            $ph = implode(',', array_fill(0, count($scope), '?'));
            $where[] = "assigned_user_id IN ($ph)";
            $params = $scope;
        }
        $whereSql = implode(' AND ', $where);
        $pending = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE $whereSql AND status NOT IN ('completed','cancelled')", $params);
        $completed = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE $whereSql AND status='completed'", $params);
        $overdue = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE $whereSql AND deadline < NOW() AND status NOT IN ('completed','cancelled')", $params);
        $upcoming = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE $whereSql AND deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) AND status NOT IN ('completed','cancelled')", $params);
        return compact('pending', 'completed', 'overdue', 'upcoming');
    }
}
