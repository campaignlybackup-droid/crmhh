<?php

Permission::require('audit.view');

$filters = [
    'entity_type' => $_GET['entity_type'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
];
$where = ['1=1'];
$params = [];
if ($filters['entity_type'] !== '') { $where[] = 'entity_type = ?'; $params[] = $filters['entity_type']; }
if ($filters['user_id'] !== '') { $where[] = 'user_id = ?'; $params[] = $filters['user_id']; }
$whereSql = implode(' AND ', $where);

$page = current_page_int();
$total = (int)Database::scalar("SELECT COUNT(*) FROM audit_logs WHERE $whereSql", $params);
$p = paginate_params($total, $page, 40);
$rows = Database::all(
    "SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $whereSql
     ORDER BY a.created_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
    $params
);
$entityTypes = array_column(Database::all('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type'), 'entity_type');
$users = UserModel::activeSelectList();

render_page('audit/index', compact('rows', 'p', 'filters', 'entityTypes', 'users'), 'Audit Log');
