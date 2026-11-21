<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'submit') {
    csrf_check_or_die();
    $date = !empty($_POST['report_date']) ? trim($_POST['report_date']) : date('Y-m-d');

    // Founder can update report for another team member if user_id is provided
    $isFounder = Permission::isFounder(Auth::id());
    $targetUserId = ($isFounder && !empty($_POST['user_id'])) ? (int)$_POST['user_id'] : Auth::id();

    // Check if report already exists for this user and date
    $existing = ReportModel::findForDate($targetUserId, $date);

    // Enforce 36-hour rule ONLY for BRAND NEW reports submitted by non-founders.
    // Existing reports (updates) and Founder actions are never blocked.
    if (!$isFounder && !$existing) {
        $reportStart = new DateTime($date);
        $reportStart->setTime(0, 0, 0);
        $deadline = clone $reportStart;
        $deadline->modify('+36 hours');

        $now = new DateTime();
        if ($now > $deadline) {
            Flash::error("The deadline for submitting a new report for {$date} has passed. Reports must be submitted within 36 hours.");
            redirect(url('reports', ['date' => $date]));
        }
    }

    ReportModel::upsert($targetUserId, $date, $_POST);
    Flash::success($existing ? 'Daily report updated.' : 'Daily report saved.');

    $redirectParams = ['date' => $date];
    if ($isFounder && $targetUserId !== Auth::id()) {
        $redirectParams['edit_user_id'] = $targetUserId;
    }
    redirect(url('reports', $redirectParams));
}

$canViewTeam = Permission::hasAny(['reports.view_team', 'reports.view_all']);
$isFounder = Permission::isFounder(Auth::id());

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$page = current_page_int();
[$rows, $p] = ReportModel::paginate($page, 20, $filters);

$selectedDate = (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']))
    ? $_GET['date']
    : date('Y-m-d');

$selectedUserId = ($isFounder && !empty($_GET['edit_user_id']))
    ? (int)$_GET['edit_user_id']
    : Auth::id();

$currentReport = ReportModel::findForDate($selectedUserId, $selectedDate);
$today = $currentReport; // for backwards compatibility
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

render_page('reports/index', compact(
    'rows', 'p', 'today', 'currentReport', 'selectedDate', 'selectedUserId', 'isFounder',
    'canViewTeam', 'filters', 'users', 'calYear', 'calMonth', 'monthlyReports'
), 'Daily Reports');
