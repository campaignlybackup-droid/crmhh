<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'set') {
    Permission::require('availability.manage');
    csrf_check_or_die();
    $date = $_POST['date'] ?? '';
    if ($date && strtotime($date)) {
        CalendarModel::setAvailability(Auth::id(), $date, $_POST['status'] ?? 'available', $_POST['note'] ?? null);
        Flash::success('Availability updated.');
    }
    redirect(url('availability', ['y' => date('Y', strtotime($date ?: 'now')), 'm' => date('n', strtotime($date ?: 'now'))]));
}

$year = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
$entries = CalendarModel::founderAvailabilityForMonth($year, $month);
$founders = Database::all('SELECT id, name FROM users WHERE is_founder = 1 AND deleted_at IS NULL');

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

render_page('availability/index', compact('entries', 'founders', 'year', 'month', 'prevMonth', 'prevYear', 'nextMonth', 'nextYear'), 'Founder Availability');
