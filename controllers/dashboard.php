<?php

$userId = Auth::id();
$isFounder = Auth::isFounder() || Auth::hasRole('founder');
$isManager = Auth::hasRole('manager') || !empty(UserModel::managedTeamsFor($userId));

// Check delayed operations issues and escalate to founder
OperationsIssueModel::checkDelaysAndEscalate();

// Handle lightweight live polling endpoint for auto-refresh
if (($_GET['action'] ?? '') === 'live_sync') {
    header('Content-Type: application/json');
    $leadCounts = Permission::hasAny(['leads.view', 'leads.view_all']) ? LeadModel::dashboardCounts($userId) : null;
    $taskCounts = TaskModel::dashboardCounts($userId);
    $delayedCount = count(OperationsIssueModel::allDelayedOrAtRisk());
    echo json_encode([
        'success' => true,
        'timestamp' => date('H:i:s'),
        'leads' => $leadCounts,
        'tasks' => $taskCounts,
        'delayed_issues' => $delayedCount
    ]);
    exit;
}

$leadCounts = Permission::hasAny(['leads.view', 'leads.view_all']) ? LeadModel::dashboardCounts($userId) : null;
$taskCounts = TaskModel::dashboardCounts($userId);
$overdueTasks = TaskModel::overdueList($userId, 10);

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

$myTeams = UserModel::teamsFor($userId);
$roles = Auth::roles();
$myAssignedServices = ClientModel::myAssignedServices($userId);

// =========================================================================
// 1. ALL-TIME TABLE VIEW: SHOOTS SCHEDULED (Whole time)
// =========================================================================
$shootsWhere = "1=1";
$shootsParams = [];
if (!$isFounder && !$isManager) {
    $shootsWhere .= " AND (ce.user_id = ? OR ce.created_by = ?)";
    $shootsParams[] = $userId;
    $shootsParams[] = $userId;
}

$shootsScheduled = Database::all(
    "SELECT ce.id, ce.title, ce.description, ce.start_datetime, ce.end_datetime, ce.location,
            u.name AS crew_name, c.name AS client_name, c.id AS client_id
     FROM calendar_events ce
     LEFT JOIN users u ON u.id = ce.user_id
     LEFT JOIN clients c ON (ce.related_type = 'client' AND ce.related_id = c.id)
     WHERE ce.event_type = 'shoot' AND $shootsWhere
     ORDER BY ce.start_datetime ASC",
    $shootsParams
);

// Also supplement with tasks categorized as shoots or videography
$taskShoots = Database::all(
    "SELECT t.id, t.title, t.description, t.deadline AS start_datetime, NULL AS end_datetime, '' AS location,
            u.name AS crew_name, c.name AS client_name, c.id AS client_id
     FROM tasks t
     LEFT JOIN users u ON u.id = t.assigned_user_id
     LEFT JOIN clients c ON c.id = t.client_id
     LEFT JOIN services s ON s.id = t.service_id
     WHERE t.deleted_at IS NULL AND t.status NOT IN ('completed', 'cancelled')
       AND (s.slug IN ('videography', 'photography') OR t.title LIKE '%shoot%' OR t.title LIKE '%Shooting%')
     ORDER BY t.deadline ASC"
);
foreach ($taskShoots as $ts) {
    $ts['id'] = 'task_' . $ts['id'];
    $shootsScheduled[] = $ts;
}

// =========================================================================
// 2. ALL-TIME TABLE VIEW: APPROVAL PENDING (Whole time with Double Check)
// =========================================================================
$pendingApprovalsList = [];

// A. Standard approvals
$apprWhere = "a.status IN ('pending', 'needs_rectification')";
$apprParams = [];
if (!$isFounder && !$isManager) {
    $apprWhere .= " AND (a.user_id = ? OR a.editor_id = ?)";
    $apprParams[] = $userId;
    $apprParams[] = $userId;
}
$dbApprovals = Database::all(
    "SELECT a.id, a.title, a.description, a.status, a.stage, a.created_at,
            u.name AS submitter_name, ed.name AS editor_name, r.name AS reviewer_name,
            'Request' AS item_type
     FROM approvals a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN users ed ON ed.id = a.editor_id
     LEFT JOIN users r ON r.id = a.reviewer_id
     WHERE $apprWhere
     ORDER BY a.created_at DESC",
    $apprParams
);
foreach ($dbApprovals as $da) {
    $stageLabel = '1. Editor Check Pending';
    if ($da['stage'] === 'pending_manager') $stageLabel = '2. Manager Review (Manav / Lead)';
    if ($da['status'] === 'needs_rectification') $stageLabel = '⚠️ Rectification Required';
    
    $pendingApprovalsList[] = [
        'type' => 'Approval',
        'title' => $da['title'],
        'submitter' => $da['submitter_name'],
        'editor' => $da['editor_name'] ?: '—',
        'stage' => $stageLabel,
        'status' => $da['status'],
        'created_at' => $da['created_at'],
        'url' => url('approvals', ['action' => 'view', 'id' => $da['id']])
    ];
}

// B. Content Calendar double check items
$ccPending = Database::all(
    "SELECT cc.id, cc.title, cc.status, cc.post_date, cc.created_at,
            c.name AS client_name, u.name AS assignee_name, ed.name AS editor_name,
            cc.rectification_notes
     FROM content_calendar cc
     JOIN clients c ON c.id = cc.client_id
     LEFT JOIN users u ON u.id = cc.assigned_to
     LEFT JOIN users ed ON ed.id = cc.editor_checked_by
     WHERE cc.status IN ('pending_editor_check', 'pending_approval', 'manager_review', 'needs_rectification')
     ORDER BY cc.post_date ASC"
);
foreach ($ccPending as $cc) {
    $stage = '1. Editor Check Pending';
    if ($cc['status'] === 'manager_review') $stage = '2. Manager Review (Manav / Lead)';
    if ($cc['status'] === 'needs_rectification') $stage = '⚠️ Rectification Required';

    $pendingApprovalsList[] = [
        'type' => 'Content (' . $cc['client_name'] . ')',
        'title' => $cc['title'],
        'submitter' => $cc['assignee_name'] ?: 'Team Member',
        'editor' => $cc['editor_name'] ?: 'Pending',
        'stage' => $stage,
        'status' => $cc['status'],
        'created_at' => $cc['created_at'],
        'url' => url('content_calendar', ['client_id' => $cc['client_id'] ?? 0])
    ];
}

// =========================================================================
// 3. ALL-TIME TABLE VIEW: DUE TOMORROW (Tasks, Content, Shoots)
// =========================================================================
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));
$dueTomorrowList = [];

// A. Tasks due tomorrow
$tasksDueTomorrow = Database::all(
    "SELECT t.id, t.title, t.deadline, t.priority, t.status, c.name AS client_name, u.name AS assignee_name
     FROM tasks t
     LEFT JOIN clients c ON c.id = t.client_id
     LEFT JOIN users u ON u.id = t.assigned_user_id
     WHERE DATE(t.deadline) = ? AND t.status NOT IN ('completed', 'cancelled') AND t.deleted_at IS NULL
     ORDER BY t.priority DESC",
    [$tomorrowDate]
);
foreach ($tasksDueTomorrow as $tdt) {
    $dueTomorrowList[] = [
        'type' => 'Task',
        'title' => $tdt['title'],
        'client' => $tdt['client_name'] ?: '—',
        'assignee' => $tdt['assignee_name'] ?: 'Unassigned',
        'due' => $tdt['deadline'],
        'status' => $tdt['status'],
        'url' => url('tasks', ['action' => 'view', 'id' => $tdt['id']])
    ];
}

// B. Content due tomorrow
$contentDueTomorrow = Database::all(
    "SELECT cc.id, cc.title, cc.post_date, cc.status, c.name AS client_name, u.name AS assignee_name
     FROM content_calendar cc
     JOIN clients c ON c.id = cc.client_id
     LEFT JOIN users u ON u.id = cc.assigned_to
     WHERE cc.post_date = ? AND cc.status != 'published'",
    [$tomorrowDate]
);
foreach ($contentDueTomorrow as $cdt) {
    $dueTomorrowList[] = [
        'type' => 'Content Post',
        'title' => $cdt['title'],
        'client' => $cdt['client_name'],
        'assignee' => $cdt['assignee_name'] ?: 'Unassigned',
        'due' => $cdt['post_date'],
        'status' => $cdt['status'],
        'url' => url('content_calendar')
    ];
}

// C. Follow-ups due tomorrow
$leadsDueTomorrow = Database::all(
    "SELECT l.id, l.name, l.company, l.next_followup_date, u.name AS assignee_name, ls.name AS status_name
     FROM leads l
     JOIN lead_statuses ls ON ls.id = l.status_id
     LEFT JOIN users u ON u.id = l.assigned_user_id
     WHERE l.next_followup_date = ? AND l.deleted_at IS NULL AND ls.is_won = 0 AND ls.is_lost = 0",
    [$tomorrowDate]
);
foreach ($leadsDueTomorrow as $ldt) {
    $dueTomorrowList[] = [
        'type' => 'Lead Follow-up',
        'title' => $ldt['name'] . ($ldt['company'] ? " ({$ldt['company']})" : ''),
        'client' => 'Sales',
        'assignee' => $ldt['assignee_name'] ?: 'Unassigned',
        'due' => $ldt['next_followup_date'],
        'status' => $ldt['status_name'],
        'url' => url('leads', ['action' => 'view', 'id' => $ldt['id']])
    ];
}

// =========================================================================
// 4. AT RISK & CLIENT DELAYS (FOUNDER ESCALATION QUEUE)
// =========================================================================
$clientAtRiskItems = [];

// A. Delayed / Overdue Client Tasks
$overdueClientTasks = Database::all(
    "SELECT t.id, t.title, t.deadline, DATEDIFF(NOW(), t.deadline) AS days_delayed,
            c.name AS client_name, u.name AS responsible_name, t.priority
     FROM tasks t
     JOIN clients c ON c.id = t.client_id
     LEFT JOIN users u ON u.id = t.assigned_user_id
     WHERE t.deadline < NOW() AND t.status NOT IN ('completed', 'cancelled') AND t.deleted_at IS NULL
     ORDER BY days_delayed DESC LIMIT 20"
);
foreach ($overdueClientTasks as $oct) {
    $clientAtRiskItems[] = [
        'source' => 'Client Task Overdue',
        'client' => $oct['client_name'],
        'item' => $oct['title'],
        'responsible' => $oct['responsible_name'] ?: 'Unassigned',
        'delay' => (int)$oct['days_delayed'] . ' days overdue',
        'severity' => (int)$oct['days_delayed'] > 3 ? 'critical' : 'major',
        'action_url' => url('tasks', ['action' => 'view', 'id' => $oct['id']])
    ];
}

// B. Delayed Operations Issues
$delayedOpsIssues = OperationsIssueModel::allDelayedOrAtRisk();
foreach ($delayedOpsIssues as $doi) {
    $clientAtRiskItems[] = [
        'source' => 'Operations Error Delay',
        'client' => $doi['client_name'] ?: 'Agency Operations',
        'item' => $doi['issue_title'],
        'responsible' => $doi['responsible_name'],
        'delay' => $doi['due_date'] ? 'Target: ' . format_date($doi['due_date']) : 'Pending Rectification',
        'severity' => $doi['severity'],
        'action_url' => url('operations_issues', ['action' => 'view', 'id' => $doi['id']])
    ];
}

// C. Delayed Content Posts past post_date
$delayedContent = Database::all(
    "SELECT cc.id, cc.title, cc.post_date, DATEDIFF(NOW(), cc.post_date) AS days_delayed,
            c.name AS client_name, u.name AS responsible_name
     FROM content_calendar cc
     JOIN clients c ON c.id = cc.client_id
     LEFT JOIN users u ON u.id = cc.assigned_to
     WHERE cc.post_date < CURDATE() AND cc.status NOT IN ('published', 'scheduled')
     ORDER BY days_delayed DESC LIMIT 15"
);
foreach ($delayedContent as $dc) {
    $clientAtRiskItems[] = [
        'source' => 'Missed Content Post Date',
        'client' => $dc['client_name'],
        'item' => $dc['title'],
        'responsible' => $dc['responsible_name'] ?: 'Unassigned',
        'delay' => (int)$dc['days_delayed'] . ' days overdue',
        'severity' => 'major',
        'action_url' => url('content_calendar', ['client_id' => $dc['client_id'] ?? 0])
    ];
}

// =========================================================================
// 5. MASTER PENDING ACTIONS (Now available for everyone, scoped!)
// =========================================================================
$masterPendingActions = [];
try {
    $leadFilter = $isFounder ? "1=1" : "l.assigned_user_id = $userId";
    $pendingLeads = Database::all(
        "SELECT l.id, l.name, l.company, u.name as assignee, ls.name as status_name 
         FROM leads l 
         LEFT JOIN users u ON u.id = l.assigned_user_id 
         JOIN lead_statuses ls ON ls.id = l.status_id 
         WHERE ls.slug IN ('new-leads', 'call-scheduled', 'follow-up', 'almost-closed') 
           AND l.deleted_at IS NULL AND $leadFilter 
         ORDER BY l.created_at DESC LIMIT 30"
    );
    foreach ($pendingLeads as $l) {
        $masterPendingActions[] = [
            'type' => 'Lead',
            'title' => $l['name'] . ($l['company'] ? " ({$l['company']})" : ''),
            'context' => 'Sales',
            'assigned_user_id' => $l['assignee'] ?: 'Unassigned',
            'status' => $l['status_name'],
            'url' => url('leads', ['action' => 'view', 'id' => $l['id']]),
            'timestamp' => 0
        ];
    }
} catch (Throwable $e) { /* ignore */ }

render_page('dashboard/index', compact(
    'leadCounts', 'taskCounts', 'overdueTasks', 'renewals', 'activeClientsCount',
    'pendingLeaveApprovals', 'myLeave', 'todaysReport', 'managedTeams', 'teamWorkload',
    'recentActivity', 'myTeams', 'roles', 'clientsVisible', 'myAssignedServices',
    'shootsScheduled', 'pendingApprovalsList', 'dueTomorrowList', 'clientAtRiskItems', 'masterPendingActions'
), 'Agency Dashboard');
