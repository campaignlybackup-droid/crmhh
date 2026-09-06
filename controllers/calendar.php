<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'create') {
    csrf_check_or_die();
    $v = Validator::make($_POST)->required('title', 'Title')->required('start_datetime', 'Start');
    if (!$v->fails()) {
        CalendarModel::create([
            'user_id' => Auth::id(), 'title' => $_POST['title'], 'description' => $_POST['description'] ?? '',
            'event_type' => 'event', 'start_datetime' => $_POST['start_datetime'], 'end_datetime' => $_POST['end_datetime'] ?? null,
            'location' => $_POST['location'] ?? null,
        ]);
        Flash::success('Event added.');
    } else {
        Flash::error($v->firstError());
    }
    redirect(url('calendar', ['y' => date('Y', strtotime($_POST['start_datetime'] ?? 'now')), 'm' => date('n', strtotime($_POST['start_datetime'] ?? 'now'))]));
}

if ($action === 'delete') {
    csrf_check_or_die();
    CalendarModel::delete((int)($_POST['id'] ?? 0), Auth::id());
    Flash::success('Event removed.');
    redirect(url('calendar'));
}

$year = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
$viewUserId = Auth::id();
if (Permission::has('calendar.view_all') && !empty($_GET['user_id'])) {
    if ($_GET['user_id'] === 'all') {
        $viewUserId = 'all';
    } else {
        $viewUserId = (int)$_GET['user_id'];
    }
}

$events = CalendarModel::eventsForMonth($viewUserId, $year, $month);

$users = [];
if (Permission::has('calendar.view_all')) {
    $allUsers = UserModel::activeSelectList();
    $isFounder = Auth::hasRole('founder');
    if ($isFounder) {
        $users = $allUsers;
    } else {
        // Filter out founder from the list
        foreach ($allUsers as $u) {
            $isUFounder = (int)Database::scalar('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = ?', [$u['id'], 'founder']);
            if (!$isUFounder) {
                $users[] = $u;
            }
        }
    }
}

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

render_page('calendar/index', compact('events', 'year', 'month', 'viewUserId', 'users', 'prevMonth', 'prevYear', 'nextMonth', 'nextYear'), 'Calendar');
