<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-1 month'));
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+1 month'));

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

$events = [];

try {
    // 1. Tasks
    $tasksSql = "SELECT t.id, t.task_name, t.due_date, t.status 
                 FROM tasks t";
    if (!$isFounder) {
        $tasksSql .= " WHERE t.id IN (SELECT task_id FROM task_assignments WHERE user_id = $user_id)";
    }
    $tasksSql .= " AND t.due_date BETWEEN ? AND ?";
    
    $stmt = $pdo->prepare($tasksSql);
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'id' => 'task_' . $row['id'],
            'title' => 'Task: ' . $row['task_name'],
            'start' => $row['due_date'],
            'allDay' => true,
            'backgroundColor' => '#0d6efd',
            'borderColor' => '#0d6efd',
            'url' => 'task_view.php?id=' . $row['id']
        ];
    }

    // 2. Leaves (Approved leaves)
    $leaveSql = "SELECT l.id, u.username, l.leave_date 
                 FROM leave_requests l 
                 JOIN users u ON l.user_id = u.id 
                 WHERE l.status = 'Approved' AND l.leave_date BETWEEN ? AND ?";
    if (!$isFounder) {
        $leaveSql .= " AND l.user_id IN ($visibleIdsStr)";
    }
    $stmt = $pdo->prepare($leaveSql);
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'id' => 'leave_' . $row['id'],
            'title' => $row['username'] . ' on Leave',
            'start' => $row['leave_date'],
            'allDay' => true,
            'backgroundColor' => '#ffc107',
            'borderColor' => '#ffc107'
        ];
    }

    // 3. Availability (Founder's availability visible to all)
    // Find founder users
    $founderSql = "SELECT user_id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE r.name = 'Founder'";
    $founderStmt = $pdo->query($founderSql);
    $founderIds = $founderStmt->fetchAll(PDO::FETCH_COLUMN);
    $founderIdsStr = empty($founderIds) ? '0' : implode(',', $founderIds);
    
    // Fetch availability for current user OR founders
    $avSql = "SELECT a.id, a.user_id, a.start_time, a.end_time, a.status, a.notes, u.username 
              FROM availability a 
              JOIN users u ON a.user_id = u.id 
              WHERE (a.user_id = ? OR a.user_id IN ($founderIdsStr)) 
              AND a.start_time >= ? AND a.start_time <= ?";
    
    $stmt = $pdo->prepare($avSql);
    $stmt->execute([$user_id, $start . ' 00:00:00', $end . ' 23:59:59']);
    foreach ($stmt->fetchAll() as $row) {
        $isOwn = ($row['user_id'] == $user_id);
        $title = $isOwn ? $row['status'] . ($row['notes'] ? ': ' . $row['notes'] : '') : "Founder " . $row['status'];
        
        $color = '#198754'; // Available - Green
        if ($row['status'] == 'Busy' || $row['status'] == 'Unavailable') $color = '#dc3545'; // Red
        if ($row['status'] == 'Meeting') $color = '#0dcaf0'; // Cyan
        
        $events[] = [
            'id' => 'avail_' . $row['id'],
            'title' => $title,
            'start' => $row['start_time'],
            'end' => $row['end_time'],
            'allDay' => false,
            'backgroundColor' => $color,
            'borderColor' => $color
        ];
    }

} catch (Exception $e) {}

echo json_encode($events);
