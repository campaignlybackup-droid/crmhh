<?php

$userId = Auth::id();

$leadCounts = Permission::hasAny(['leads.view', 'leads.view_all']) ? LeadModel::dashboardCounts($userId) : null;
$taskCounts = TaskModel::dashboardCounts($userId);
$overdueTasks = TaskModel::overdueList($userId, 8);

$clientsVisible = Permission::hasAny(['clients.view', 'clients.view_all']);
$renewals = $clientsVisible ? ClientModel::upcomingRenewals(30, $userId) : [];
$activeClientsCount = null;
if ($clientsVisible) {
    $vis = Permission::clientVisibility($userId);
    $activeClientsCount = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL AND status='active' AND ({$vis['sql']})", $vis['params']);
}

$pendingLeaveApprovals = [];
if (Permission::hasAny(['leave.approve_all', 'leave.approve_team'])) {
    [$pendingLeaveApprovals] = LeaveModel::paginate(1, 5, ['status' => 'pending'], $userId);
}

$myLeave = Database::all('SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 3', [$userId]);

$todaysReport = ReportModel::findForDate($userId, date('Y-m-d'));

$managedTeams = UserModel::managedTeamsFor($userId);
$teamWorkload = [];
if (!empty($managedTeams)) {
    $teamWorkload = TeamModel::workload((int)$managedTeams[0]['id']);
}

$recentActivity = Database::all(
    "SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 10"
);
if (!Permission::has('leads.view_all') && !Permission::has('tasks.view_all')) {
    $scope = Permission::managedUserIds($userId);
    $recentActivity = Database::all(
        "SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IN (" . implode(',', array_fill(0, count($scope), '?')) . ") ORDER BY a.created_at DESC LIMIT 10",
        $scope
    );
}

$myTeams = UserModel::teamsFor($userId);
$roles = Auth::roles();

$myAssignedServices = ClientModel::myAssignedServices($userId);

render_page('dashboard/index', compact(
    'leadCounts', 'taskCounts', 'overdueTasks', 'renewals', 'activeClientsCount',
    'pendingLeaveApprovals', 'myLeave', 'todaysReport', 'managedTeams', 'teamWorkload',
    'recentActivity', 'myTeams', 'roles', 'clientsVisible', 'myAssignedServices'
), 'Dashboard');
