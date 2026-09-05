<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$roles = getUserRoles($pdo, $user_id);

$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);
$hasFilter = !empty($visibleIdsStr);

// Metrics
$pendingTasksCount = 0;
$upcomingDeadlines = [];
$recentActivities = [];
$revenue = 0;
$pendingRevenue = 0;
$conversionRate = 0;
$totalLeads = 0;
$teamPerformance = [];
$pendingTasks = [];

try {
    // KPI: Revenue Generated
    $revSql = "SELECT SUM(i.amount) FROM invoices i";
    if ($hasFilter) {
        $revSql .= " JOIN clients c ON i.client_id = c.id WHERE i.status = 'Paid' AND i.deleted_at IS NULL AND c.assigned_to IN ($visibleIdsStr)";
    } else {
        $revSql .= " WHERE i.status = 'Paid' AND i.deleted_at IS NULL";
    }
    $revenue = $pdo->query($revSql)->fetchColumn() ?: 0;

    // KPI: Pending Revenue
    $pendRevSql = "SELECT SUM(i.amount) FROM invoices i";
    if ($hasFilter) {
        $pendRevSql .= " JOIN clients c ON i.client_id = c.id WHERE i.status != 'Paid' AND i.deleted_at IS NULL AND c.assigned_to IN ($visibleIdsStr)";
    } else {
        $pendRevSql .= " WHERE i.status != 'Paid' AND i.deleted_at IS NULL";
    }
    $pendingRevenue = $pdo->query($pendRevSql)->fetchColumn() ?: 0;

    // KPI: Lead Conversion
    $leadCond = $hasFilter ? "WHERE deleted_at IS NULL AND assigned_to IN ($visibleIdsStr)" : "WHERE deleted_at IS NULL";
    $totalLeads = $pdo->query("SELECT COUNT(*) FROM leads $leadCond")->fetchColumn();
    $wonLeads = $pdo->query("SELECT COUNT(*) FROM leads $leadCond AND status = 'Won'")->fetchColumn();
    $conversionRate = ($totalLeads > 0) ? round(($wonLeads / $totalLeads) * 100, 1) : 0;

    // KPI: Team Performance Leaderboard
    $teamQuery = "
        SELECT 
            u.id,
            u.username, 
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.deleted_at IS NULL) as total_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'Done' AND t.deleted_at IS NULL) as completed_tasks,
            (SELECT COUNT(*) FROM leads l WHERE l.assigned_to = u.id AND l.status = 'Won' AND l.deleted_at IS NULL) as won_leads,
            (SELECT COALESCE(SUM(deal_value), 0) FROM leads l WHERE l.assigned_to = u.id AND l.status = 'Won' AND l.deleted_at IS NULL) as won_value
        FROM users u 
        WHERE u.deleted_at IS NULL
    ";
    if ($hasFilter) {
        $teamQuery .= " AND u.id IN ($visibleIdsStr)";
    }
    $teamQuery .= " ORDER BY won_value DESC, completed_tasks DESC LIMIT 5";
    $teamPerformance = $pdo->query($teamQuery)->fetchAll(PDO::FETCH_ASSOC);

    // 1. Pending Tasks Logic
    $tasksSql = "SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.status != 'Done' AND t.deleted_at IS NULL";
    if ($hasFilter) {
        $tasksSql .= " AND t.id IN (SELECT task_id FROM task_assignments WHERE user_id IN ($visibleIdsStr))";
    }
    $tasksSql .= " ORDER BY t.due_date ASC";
    $stmt = $pdo->prepare($tasksSql);
    $stmt->execute();
    $pendingTasks = $stmt->fetchAll();
    $pendingTasksCount = count($pendingTasks);

    // 2. Upcoming Deadlines
    $deadlinesSql = "SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status != 'Done' AND t.deleted_at IS NULL";
    if ($hasFilter) {
        $deadlinesSql .= " AND t.id IN (SELECT task_id FROM task_assignments WHERE user_id IN ($visibleIdsStr))";
    }
    $deadlinesSql .= " ORDER BY t.due_date ASC LIMIT 5";
    $stmt = $pdo->prepare($deadlinesSql);
    $stmt->execute();
    $upcomingDeadlines = $stmt->fetchAll();

    // 3. Recent Activities
    $activitySql = "SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id";
    if ($hasFilter) {
        $activitySql .= " WHERE a.user_id IN ($visibleIdsStr)";
    }
    $activitySql .= " ORDER BY a.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($activitySql);
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();

} catch (\Throwable $e) {
    // DB error might happen before all migrations are populated
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Welcome back, <?= h(getCurrentUsername()) ?></h3>
        <p class="text-muted mb-0">Your roles: <?= h(implode(', ', $roles)) ?></p>
    </div>
</div>

<!-- Dynamic Role-Based Widgets -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-success text-success me-3"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h3 class="mb-0 fw-bold">$<?= number_format($revenue, 2) ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase"><?= $isFounder ? 'Global Revenue' : ($isManager ? 'Team Revenue' : 'My Revenue') ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-primary text-primary me-3"><i class="bi bi-funnel-fill"></i></div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $conversionRate ?>%</h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase"><?= $isFounder ? 'Total Conversion' : ($isManager ? 'Team Conversion' : 'My Conversion') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-warning text-warning me-3"><i class="bi bi-list-task"></i></div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $pendingTasksCount ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase"><?= $isFounder ? 'Total Pending Tasks' : ($isManager ? 'Team Pending Tasks' : 'My Pending Tasks') ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($isFounder || $isManager): ?>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm bg-soft-info">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-info text-white me-3"><i class="bi bi-diagram-3-fill"></i></div>
                <div>
                    <h5 class="fw-bold mb-1">Command Center</h5>
                    <a href="users.php" class="text-decoration-none small fw-bold text-dark">Manage <?= $isFounder ? 'Agency' : 'Team' ?> &rarr;</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Tasks Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">My Pending Tasks</h5>
                <a href="tasks.php" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTasks)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success fs-1 mb-3 d-block"></i>
                        <p class="text-muted small">No pending tasks. Great job!</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush mt-2">
                        <?php foreach (array_slice($pendingTasks, 0, 6) as $task): ?>
                            <li class="list-group-item px-0 py-3 bg-transparent d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <a href="task_view.php?id=<?= $task['id'] ?>" class="text-decoration-none text-dark"><?= h($task['task_name']) ?></a>
                                    </h6>
                                    <div class="small text-muted"><?= h($task['project_name'] ?? 'General') ?></div>
                                </div>
                                <div class="badge bg-soft-warning text-warning rounded-pill px-3 py-2 border border-warning">
                                    <?= date('M d', strtotime($task['due_date'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Leaderboard Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><?= $isFounder ? 'Global Top Performers' : ($isManager ? 'Team Leaderboard' : 'My Performance Snapshot') ?></h5>
                <?php if ($isFounder || $isManager): ?>
                <a href="reports.php" class="text-decoration-none small">Full Report</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0 mt-3">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3 border-0">Member</th>
                                <th class="text-center border-0">Deals</th>
                                <th class="text-end pe-3 border-0">Tasks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($teamPerformance)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No data available.</td></tr>
                            <?php else: ?>
                                <?php foreach($teamPerformance as $member): 
                                    $pct = ($member['total_tasks'] > 0) ? round(($member['completed_tasks'] / $member['total_tasks']) * 100) : 0;
                                    $bg = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <?= h($member['username']) ?>
                                        <div class="small text-muted fw-normal" style="font-size:0.75rem;">$<?= number_format($member['won_value']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-success text-success fw-bold p-2"><?= $member['won_leads'] ?> Won</span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="fw-bold text-dark small"><?= $member['completed_tasks'] ?>/<?= $member['total_tasks'] ?></span>
                                        <div class="progress mt-1 ms-auto bg-light" style="height: 5px; width: 60px;">
                                            <div class="progress-bar <?= $bg ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Activity</h5>
            </div>
            <div class="card-body mt-3">
                <?php if (empty($recentActivities)): ?>
                    <p class="text-muted small text-center py-4">No recent activity.</p>
                <?php else: ?>
                    <div class="position-relative">
                        <?php foreach ($recentActivities as $act): ?>
                            <div class="d-flex mb-3 align-items-center p-2 rounded hover-bg-light">
                                <div class="me-3 text-primary bg-soft-primary p-2 rounded-circle">
                                    <i class="bi bi-activity"></i>
                                </div>
                                <div>
                                    <div class="small fw-bold mb-0 text-capitalize text-dark"><?= h($act['action']) ?></div>
                                    <div class="small text-muted" style="font-size: 0.8rem;"><?= h($act['username']) ?> on <?= h($act['entity_type']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
// Quick Add Modals
include 'includes/modals.php'; 
?>
<?php include 'footer.php'; ?>
