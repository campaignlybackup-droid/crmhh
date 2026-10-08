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
$isFounder = Auth::hasRole('founder') || Auth::isFounder();
$isManager = Auth::hasRole('manager') || !empty(UserModel::managedTeamsFor(Auth::id()));

if (!empty($_GET['user_id'])) {
    if ($_GET['user_id'] === 'all') {
        if ($isFounder || $isManager || Permission::has('calendar.view_all')) {
            $viewUserId = 'all';
        }
    } else {
        $reqUser = (int)$_GET['user_id'];
        if ($isFounder || Permission::has('calendar.view_all') || ($isManager && Permission::manages(Auth::id(), $reqUser))) {
            $viewUserId = $reqUser;
        }
    }
}

$events = CalendarModel::eventsForMonth($viewUserId, $year, $month);

$users = [];
if ($isFounder || $isManager || Permission::has('calendar.view_all')) {
    $users = UserModel::scopedSelectList(Auth::id());
}

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

render_page('calendar/index', compact('events', 'year', 'month', 'viewUserId', 'users', 'prevMonth', 'prevYear', 'nextMonth', 'nextYear'), 'Calendar');
