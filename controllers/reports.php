<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'submit') {
    csrf_check_or_die();
    $date = $_POST['report_date'] ?: date('Y-m-d');
    ReportModel::upsert(Auth::id(), $date, $_POST);
    Flash::success('Daily report saved.');
    redirect(url('reports'));
}

$canViewTeam = Permission::hasAny(['reports.view_team', 'reports.view_all']);
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$page = current_page_int();
[$rows, $p] = ReportModel::paginate($page, 20, $filters);
$today = ReportModel::findForDate(Auth::id(), date('Y-m-d'));
$users = $canViewTeam ? UserModel::activeSelectList() : [];

// Calendar data for Founder/Manager
$monthlyReports = [];
$calYear = (int)($_GET['year'] ?? date('Y'));
$calMonth = (int)($_GET['month'] ?? date('n'));
if ($canViewTeam) {
    $monthlyReports = ReportModel::getMonthlyReports($calYear, $calMonth);
}

// Handle AJAX request for a specific date's reports
if ($action === 'ajax_day' && $canViewTeam) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayReports = [];
    $allInMonth = ReportModel::getMonthlyReports((int)substr($date, 0, 4), (int)substr($date, 5, 2));
    if (isset($allInMonth[$date])) {
        $dayReports = $allInMonth[$date];
    }
    header('Content-Type: application/json');
    echo json_encode($dayReports);
    exit;
}

render_page('reports/index', compact('rows', 'p', 'today', 'canViewTeam', 'filters', 'users', 'calYear', 'calMonth', 'monthlyReports'), 'Daily Reports');
