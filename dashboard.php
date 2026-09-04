<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$roles = getUserRoles($pdo, $user_id);

$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Metrics
$pendingTasksCount = 0;
$upcomingDeadlines = [];
$recentActivities = [];

try {
    // 1. Pending Tasks Logic
    $tasksSql = "SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.status != 'Done'";
    if (!$isFounder) {
        $tasksSql .= " AND t.id IN (SELECT task_id FROM task_assignments WHERE user_id IN ($visibleIdsStr))";
    }
    $tasksSql .= " ORDER BY t.due_date ASC";
    $stmt = $pdo->prepare($tasksSql);
    $stmt->execute();
    $pendingTasks = $stmt->fetchAll();
    $pendingTasksCount = count($pendingTasks);

    // 2. Upcoming Deadlines
    $deadlinesSql = "SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status != 'Done'";
    if (!$isFounder) {
        $deadlinesSql .= " AND t.id IN (SELECT task_id FROM task_assignments WHERE user_id IN ($visibleIdsStr))";
    }
    $deadlinesSql .= " ORDER BY t.due_date ASC LIMIT 5";
    $stmt = $pdo->prepare($deadlinesSql);
    $stmt->execute();
    $upcomingDeadlines = $stmt->fetchAll();

    // 3. Recent Activities
    $activitySql = "SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id";
    if (!$isFounder) {
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
    <?php if ($isFounder): ?>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm bg-soft-primary">
            <div class="card-body">
                <h5 class="fw-bold">Command Center</h5>
                <p class="text-muted small">You have full visibility across all teams and projects.</p>
                <a href="users.php" class="btn btn-sm btn-primary">Manage Agency</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-warning text-warning me-3"><i class="bi bi-list-task"></i></div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $pendingTasksCount ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Pending Tasks</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Tasks Column -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">My Tasks</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTasks)): ?>
                    <p class="text-muted small text-center py-4">No pending tasks. Great job!</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($pendingTasks, 0, 8) as $task): ?>
                            <li class="list-group-item px-0 py-3 bg-transparent d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= h($task['task_name']) ?></h6>
                                    <div class="small text-muted"><?= h($task['project_name'] ?? 'General') ?></div>
                                </div>
                                <div class="badge bg-soft-warning text-warning rounded-pill">
                                    <?= date('M d', strtotime($task['due_date'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Activity Column -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivities)): ?>
                    <p class="text-muted small text-center py-4">No recent activity.</p>
                <?php else: ?>
                    <div class="position-relative">
                        <?php foreach ($recentActivities as $act): ?>
                            <div class="d-flex mb-3">
                                <div class="me-3 text-primary"><i class="bi bi-activity"></i></div>
                                <div>
                                    <div class="small fw-bold mb-0 text-capitalize"><?= h($act['action']) ?></div>
                                    <div class="small text-muted"><?= h($act['username']) ?> on <?= h($act['entity_type']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
