<?php
require_once 'functions.php';
requireLogin();

header('Content-Type: application/json');

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-1 month'));
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+1 month'));

// Filters
$isSuper = isSuperAdmin();
$filterUserId = $_GET['user_id'] ?? null;
$filterProjectId = $_GET['project_id'] ?? null;
$filterClientId = $_GET['client_id'] ?? null;
$filterType = $_GET['event_type'] ?? null;

$currentUserId = getCurrentUserId();

$events = [];

$visibleIds = getVisibleUserIds($pdo, $currentUserId);
$visibleIdsStr = implode(',', $visibleIds);

// Base condition for roles
$userCondition = $isSuper ? "" : " AND assigned_to IN ($visibleIdsStr)";
if ($isSuper && $filterUserId) {
    $userCondition = " AND assigned_to = " . (int)$filterUserId;
}

try {
    // 1. Tasks
    if (!$filterType || $filterType === 'Task') {
        $sql = "SELECT id, task_name as title, due_date as start, status FROM tasks WHERE due_date BETWEEN :start AND :end" . $userCondition;
        if ($filterProjectId) $sql .= " AND project_id = " . (int)$filterProjectId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'task_' . $row['id'],
                'title' => 'Task: ' . $row['title'],
                'start' => $row['start'],
                'allDay' => true,
                'backgroundColor' => '#4361ee', // Primary
                'borderColor' => '#4361ee',
                'url' => 'tasks.php?id=' . $row['id'],
                'extendedProps' => ['type' => 'Task', 'status' => $row['status']]
            ];
        }
    }

    // 2. Projects (Shoots)
    if (!$filterType || $filterType === 'Shoot') {
        $sql = "SELECT id, project_name as title, shoot_date as start, status FROM projects WHERE shoot_date BETWEEN :start AND :end" . $userCondition;
        if ($filterProjectId) $sql .= " AND id = " . (int)$filterProjectId;
        if ($filterClientId) $sql .= " AND client_id = " . (int)$filterClientId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'shoot_' . $row['id'],
                'title' => 'Shoot: ' . $row['title'],
                'start' => $row['start'],
                'allDay' => true,
                'backgroundColor' => '#dc3545', // Danger (Red)
                'borderColor' => '#dc3545',
                'url' => 'projects.php?id=' . $row['id'],
                'extendedProps' => ['type' => 'Shoot', 'status' => $row['status']]
            ];
        }
    }

    // 3. Projects (Deliveries)
    if (!$filterType || $filterType === 'Delivery') {
        $sql = "SELECT id, project_name as title, delivery_date as start, status FROM projects WHERE delivery_date BETWEEN :start AND :end" . $userCondition;
        if ($filterProjectId) $sql .= " AND id = " . (int)$filterProjectId;
        if ($filterClientId) $sql .= " AND client_id = " . (int)$filterClientId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'delivery_' . $row['id'],
                'title' => 'Delivery: ' . $row['title'],
                'start' => $row['start'],
                'allDay' => true,
                'backgroundColor' => '#198754', // Success (Green)
                'borderColor' => '#198754',
                'url' => 'projects.php?id=' . $row['id'],
                'extendedProps' => ['type' => 'Delivery', 'status' => $row['status']]
            ];
        }
    }

    // 4. Meetings
    if (!$filterType || $filterType === 'Meeting') {
        $sql = "SELECT id, title, start_time as start, end_time as end, meeting_url FROM meetings WHERE start_time BETWEEN :start AND :end";
        if ($filterProjectId) $sql .= " AND project_id = " . (int)$filterProjectId;
        if ($filterClientId) $sql .= " AND client_id = " . (int)$filterClientId;
        
        // Employee logic for meetings - currently we don't have participants table, so we assume meetings belong to projects
        // If employee is assigned to the project, they see it.
        if (!$isSuper) {
            $sql .= " AND project_id IN (SELECT id FROM projects WHERE assigned_to IN ($visibleIdsStr))";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end . ' 23:59:59']);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'meeting_' . $row['id'],
                'title' => 'Meeting: ' . $row['title'],
                'start' => $row['start'],
                'end' => $row['end'],
                'allDay' => false,
                'backgroundColor' => '#0dcaf0', // Info (Cyan)
                'borderColor' => '#0dcaf0',
                'url' => $row['meeting_url'] ? $row['meeting_url'] : '#',
                'extendedProps' => ['type' => 'Meeting']
            ];
        }
    }

    // 5. Leaves
    if (!$filterType || $filterType === 'Leave') {
        $leaveUserCondition = $isSuper ? "" : " AND user_id = " . (int)$currentUserId;
        if ($isSuper && $filterUserId) {
            $leaveUserCondition = " AND user_id = " . (int)$filterUserId;
        }
        $sql = "SELECT id, reason as title, start_date as start, end_date as end, status FROM leave_requests WHERE start_date <= :end AND end_date >= :start" . $leaveUserCondition;
        
        // Leaves are rarely tied to projects/clients, so we skip those filters
        if (!$filterProjectId && !$filterClientId) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['start' => $start, 'end' => $end]);
            foreach ($stmt->fetchAll() as $row) {
                $events[] = [
                    'id' => 'leave_' . $row['id'],
                    'title' => 'Leave: ' . $row['title'],
                    'start' => $row['start'],
                    // FullCalendar exclusive end date logic: add 1 day
                    'end' => date('Y-m-d', strtotime($row['end'] . ' +1 day')),
                    'allDay' => true,
                    'backgroundColor' => '#ffc107', // Warning (Yellow)
                    'borderColor' => '#ffc107',
                    'textColor' => '#000',
                    'extendedProps' => ['type' => 'Leave', 'status' => $row['status']]
                ];
            }
        }
    }

    // 6. Company Holidays
    if (!$filterType || $filterType === 'Holiday') {
        if (!$filterProjectId && !$filterClientId && !$filterUserId) {
            $sql = "SELECT id, title, date as start FROM company_holidays WHERE date BETWEEN :start AND :end";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['start' => $start, 'end' => $end]);
            foreach ($stmt->fetchAll() as $row) {
                $events[] = [
                    'id' => 'holiday_' . $row['id'],
                    'title' => 'Holiday: ' . $row['title'],
                    'start' => $row['start'],
                    'allDay' => true,
                    'backgroundColor' => '#6c757d', // Secondary (Gray)
                    'borderColor' => '#6c757d',
                    'extendedProps' => ['type' => 'Holiday']
                ];
            }
        }
    }

    // 7. General Calendar Events
    if (!$filterType || $filterType === 'Custom') {
        $evtUserCondition = $isSuper ? " AND (user_id IS NULL OR user_id = user_id)" : " AND (user_id IS NULL OR user_id = " . (int)$currentUserId . ")";
        if ($isSuper && $filterUserId) {
            $evtUserCondition = " AND user_id = " . (int)$filterUserId;
        }

        $sql = "SELECT id, title, start_time as start, end_time as end, type FROM calendar_events WHERE start_time BETWEEN :start AND :end" . $evtUserCondition;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end . ' 23:59:59']);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'custom_' . $row['id'],
                'title' => $row['title'],
                'start' => $row['start'],
                'end' => $row['end'] !== $row['start'] ? $row['end'] : null,
                'allDay' => (strpos($row['start'], '00:00:00') !== false),
                'backgroundColor' => '#9c27b0', // Purple
                'borderColor' => '#9c27b0',
                'extendedProps' => ['type' => $row['type']]
            ];
        }
    }

} catch (Exception $e) {
    // If table doesn't exist, ignore and just return empty or current fetched events
}

echo json_encode($events);
