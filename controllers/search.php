<?php

$q = trim($_GET['q'] ?? '');
$results = ['leads' => [], 'clients' => [], 'tasks' => [], 'users' => []];

if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';

    if (Permission::hasAny(['leads.view', 'leads.view_all'])) {
        $scope = Permission::scopeIds('leads.view_all');
        $where = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR lead_code LIKE ?) AND deleted_at IS NULL';
        $params = [$like, $like, $like, $like];
        if ($scope !== null) {
            if (!empty($scope)) {
                $ph = implode(',', array_fill(0, count($scope), '?'));
                $where .= " AND (assigned_user_id IN ($ph) OR created_by IN ($ph))";
                $params = array_merge($params, $scope, $scope);
            } else {
                $where .= ' AND 0=1';
            }
        }
        $results['leads'] = Database::all("SELECT * FROM leads WHERE $where LIMIT 15", $params);
    }

    if (Permission::hasAny(['clients.view', 'clients.view_all'])) {
        $vis = Permission::clientVisibility();
        $where = "(name LIKE ? OR company LIKE ? OR client_code LIKE ?) AND deleted_at IS NULL AND ({$vis['sql']})";
        $params = array_merge([$like, $like, $like], $vis['params']);
        $results['clients'] = Database::all("SELECT * FROM clients WHERE $where LIMIT 15", $params);
    }

    if (Permission::hasAny(['tasks.view', 'tasks.view_all'])) {
        $scope = Permission::scopeIds('tasks.view_all');
        $where = '(title LIKE ? OR task_code LIKE ?) AND deleted_at IS NULL';
        $params = [$like, $like];
        if ($scope !== null) {
            if (!empty($scope)) {
                $ph = implode(',', array_fill(0, count($scope), '?'));
                $where .= " AND assigned_user_id IN ($ph)";
                $params = array_merge($params, $scope);
            } else {
                $where .= ' AND 0=1';
            }
        }
        $results['tasks'] = Database::all("SELECT * FROM tasks WHERE $where LIMIT 15", $params);
    }

    if (Permission::has('users.manage')) {
        $results['users'] = Database::all(
            "SELECT * FROM users WHERE (name LIKE ? OR email LIKE ? OR employee_code LIKE ?) AND deleted_at IS NULL LIMIT 15",
            [$like, $like, $like]
        );
    }
}

render_page('search/index', compact('q', 'results'), 'Search');
