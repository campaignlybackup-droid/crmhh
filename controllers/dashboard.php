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
    if (empty($scope)) {
        $recentActivity = [];
    } else {
        $recentActivity = Database::all(
            "SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IN (" . implode(',', array_fill(0, count($scope), '?')) . ") ORDER BY a.created_at DESC LIMIT 10",
            $scope
        );
    }
}

$myTeams = UserModel::teamsFor($userId);
$roles = Auth::roles();

$myAssignedServices = ClientModel::myAssignedServices($userId);

$masterPendingActions = [];
if (Auth::hasRole('founder')) {
    $pendingLeads = Database::all("SELECT l.id, l.company_name as title, u.name as assignee, l.status FROM leads l LEFT JOIN users u ON u.id = l.assigned_user_id WHERE l.status IN ('new', 'follow_up') AND l.deleted_at IS NULL ORDER BY l.created_at ASC");
    foreach ($pendingLeads as $l) {
        $masterPendingActions[] = [
            'type' => 'Lead',
            'title' => $l['title'] ?: 'Unnamed Lead',
            'context' => '—',
            'assigned_user_id' => $l['assignee'] ?: 'Unassigned',
            'status' => $l['status'],
            'url' => url('leads', ['action' => 'view', 'id' => $l['id']]),
            'timestamp' => 0
        ];
    }
    
    $pendingTasks = Database::all("SELECT t.id, t.title, c.name as context, u.name as assignee, t.status, t.deadline FROM tasks t LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN users u ON u.id = t.assigned_user_id WHERE t.status NOT IN ('completed', 'cancelled') AND t.deleted_at IS NULL ORDER BY t.deadline ASC LIMIT 50");
    foreach ($pendingTasks as $t) {
        $masterPendingActions[] = [
            'type' => 'Task',
            'title' => $t['title'],
            'context' => $t['context'] ?: '—',
            'assigned_user_id' => $t['assignee'] ?: 'Unassigned',
            'status' => $t['status'],
            'url' => url('tasks', ['action' => 'view', 'id' => $t['id']]),
            'timestamp' => strtotime($t['deadline'])
        ];
    }
    
    $pendingProposals = Database::all("SELECT p.id, p.title, c.name as context, u.name as assignee, p.status, p.deadline_at FROM proposals p LEFT JOIN clients c ON c.id = p.client_id LEFT JOIN users u ON u.id = p.assigned_user_id WHERE p.status IN ('pending', 'in_progress') ORDER BY p.deadline_at ASC");
    foreach ($pendingProposals as $p) {
        $masterPendingActions[] = [
            'type' => 'Proposal',
            'title' => $p['title'],
            'context' => $p['context'] ?: '—',
            'assigned_user_id' => $p['assignee'] ?: 'Unassigned',
            'status' => $p['status'],
            'url' => url('proposals', ['action' => 'view', 'id' => $p['id']]),
            'timestamp' => strtotime($p['deadline_at'])
        ];
    }
    
    $pendingApprovals = Database::all("SELECT a.id, a.title, '' as context, u.name as assignee, a.status, a.created_at FROM approvals a LEFT JOIN users u ON u.id = a.user_id WHERE a.status = 'pending' ORDER BY a.created_at ASC");
    foreach ($pendingApprovals as $a) {
        $masterPendingActions[] = [
            'type' => 'Approval',
            'title' => $a['title'],
            'context' => '—',
            'assigned_user_id' => $a['assignee'] ?: 'Unassigned',
            'status' => $a['status'],
            'url' => url('approvals', ['action' => 'view', 'id' => $a['id']]),
            'timestamp' => strtotime($a['created_at'])
        ];
    }
    
    $pendingContent = Database::all("SELECT cc.id, cc.title, c.name as context, u.name as assignee, cc.status, cc.post_date FROM content_calendar cc LEFT JOIN clients c ON c.id = cc.client_id LEFT JOIN users u ON u.id = cc.assigned_to WHERE cc.status IN ('draft', 'pending_approval') ORDER BY cc.post_date ASC");
    foreach ($pendingContent as $cc) {
        $masterPendingActions[] = [
            'type' => 'Content',
            'title' => $cc['title'],
            'context' => $cc['context'] ?: '—',
            'assigned_user_id' => $cc['assignee'] ?: 'Unassigned',
            'status' => $cc['status'],
            'url' => url('content_calendar', ['client_id' => $cc['client_id'] ?? 0]),
            'timestamp' => strtotime($cc['post_date'])
        ];
    }
    
    // Sort all by timestamp if applicable
    usort($masterPendingActions, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });
}

render_page('dashboard/index', compact(
    'leadCounts', 'taskCounts', 'overdueTasks', 'renewals', 'activeClientsCount',
    'pendingLeaveApprovals', 'myLeave', 'todaysReport', 'managedTeams', 'teamWorkload',
    'recentActivity', 'myTeams', 'roles', 'clientsVisible', 'myAssignedServices', 'masterPendingActions'
), 'Dashboard');
