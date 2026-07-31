<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();
$db_error = false;

if ($isSuper) {
    try {
        // Superadmin Queries
        $totalRevenue = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'Paid'")->fetchColumn() ?: 0;
        $outstanding = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status != 'Paid'")->fetchColumn() ?: 0;
        $activeProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status != 'Delivered'")->fetchColumn() ?: 0;
        
        $todayAttendance = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM daily_work WHERE DATE(work_date) = CURDATE()")->fetchColumn() ?: 0;
        
        $activityLog = $pdo->query("SELECT a.*, u.username FROM activity_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 15")->fetchAll();
        
        $shootsQuery = "SELECT p.*, c.client_name, u.username as assigned_user FROM projects p LEFT JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.assigned_to = u.id WHERE p.shoot_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY p.shoot_date ASC LIMIT 5";
        $upcomingShoots = $pdo->query($shootsQuery)->fetchAll();
        
        // Overdue Check
        $todayNotifs = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE() AND message LIKE '%overdue%'");
        $todayNotifs->execute();
        if ($todayNotifs->fetchColumn() == 0) {
            $overdueTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status != 'Done'")->fetchColumn();
            if ($overdueTasks > 0) notifySuperAdmins($pdo, "$overdueTasks tasks are overdue!");
            
            $overdueProj = $pdo->query("SELECT COUNT(*) FROM projects WHERE delivery_date < CURDATE() AND status != 'Delivered'")->fetchColumn();
            if ($overdueProj > 0) notifySuperAdmins($pdo, "$overdueProj projects have missed delivery dates!");
            
            $missedLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE next_action_date < CURDATE() AND status NOT IN ('Won', 'Lost')")->fetchColumn();
            if ($missedLeads > 0) notifySuperAdmins($pdo, "$missedLeads leads have missed their next action date!");
        }
    } catch (PDOException $e) {
        $db_error = true;
        $totalRevenue = $outstanding = $activeProjects = $todayAttendance = 0;
        $activityLog = $upcomingShoots = [];
    }
} else {
    try {
        // Normal User Queries
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE status != 'Delivered' AND assigned_to = ?");
        $stmt->execute([$user_id]);
        $activeProjects = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status != 'Done' AND assigned_to = ?");
        $stmt->execute([$user_id]);
        $pendingTasks = $stmt->fetchColumn() ?: 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_work WHERE user_id = ? AND MONTH(work_date) = MONTH(CURDATE()) AND YEAR(work_date) = YEAR(CURDATE())");
        $stmt->execute([$user_id]);
        $monthPresence = $stmt->fetchColumn() ?: 0;
        
        $tasksQuery = "SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.status != 'Done' AND t.assigned_to = ? ORDER BY t.due_date ASC LIMIT 10";
        $stmt = $pdo->prepare($tasksQuery);
        $stmt->execute([$user_id]);
        $myTasks = $stmt->fetchAll();
        
        $shootsQuery = "SELECT p.*, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.assigned_to = ? AND p.shoot_date >= CURDATE() ORDER BY p.shoot_date ASC LIMIT 5";
        $stmt = $pdo->prepare($shootsQuery);
        $stmt->execute([$user_id]);
        $upcomingShoots = $stmt->fetchAll();
    } catch (PDOException $e) {
        $db_error = true;
        $activeProjects = $pendingTasks = $monthPresence = 0;
        $myTasks = $upcomingShoots = [];
    }
}

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold">Dashboard</h3>
        <p class="text-muted">Welcome back, <?= h(getCurrentUsername()) ?>!</p>
    </div>
</div>

<?php if ($isSuper): ?>
<!-- SUPERADMIN DASHBOARD -->
<div class="row">
    <!-- Widgets -->
    <div class="col-md-3 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">Total Revenue</h6>
                    <h3 class="mb-0 fw-bold text-success">AED <?= number_format($totalRevenue) ?></h3>
                </div>
                <div class="widget-icon text-success bg-soft-success rounded p-3">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">Outstanding</h6>
                    <h3 class="mb-0 fw-bold text-warning">AED <?= number_format($outstanding) ?></h3>
                </div>
                <div class="widget-icon text-warning bg-soft-warning rounded p-3">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">Active Projects</h6>
                    <h3 class="mb-0 fw-bold"><?= $activeProjects ?></h3>
                </div>
                <div class="widget-icon text-primary bg-soft-primary rounded p-3">
                    <i class="bi bi-briefcase-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">Today's Attendance</h6>
                    <h3 class="mb-0 fw-bold"><?= $todayAttendance ?> <span class="fs-6 text-muted fw-normal">Present</span></h3>
                </div>
                <div class="widget-icon text-info bg-soft-info rounded p-3">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Global Activity Log -->
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity"></i> Global Activity Stream</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($activityLog)): ?>
                        <div class="list-group-item text-center text-muted py-4">No recent activity.</div>
                    <?php else: ?>
                        <?php foreach ($activityLog as $log): ?>
                        <div class="list-group-item list-group-item-action py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-bold text-primary"><?= h($log['action_type']) ?></h6>
                                <small class="text-muted"><?= date('M d, H:i', strtotime($log['created_at'])) ?></small>
                            </div>
                            <p class="mb-1 small">
                                <strong><?= h($log['username'] ?? 'System') ?></strong> on <?= h($log['entity_type']) ?> 
                                <?php if($log['entity_id']): ?>#<?= h($log['entity_id']) ?><?php endif; ?>
                            </p>
                            <?php if ($log['details']): ?>
                                <small class="text-muted d-block bg-light p-2 rounded mt-2 border"><?= h($log['details']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Shoots Table -->
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>Upcoming Shoots (Next 14 Days)</span>
                <a href="projects.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Project / Client</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingShoots)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-4">No upcoming shoots scheduled.</td></tr>
                            <?php else: ?>
                                <?php foreach ($upcomingShoots as $shoot): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= h($shoot['project_name']) ?></div>
                                        <div class="small text-muted"><?= h($shoot['client_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-primary fw-bold"><?= date('M d, Y', strtotime($shoot['shoot_date'])) ?></div>
                                        <div class="small text-muted"><i class="bi bi-person"></i> <?= h($shoot['assigned_user'] ?? 'N/A') ?></div>
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
</div>

<?php else: ?>
<!-- NORMAL USER DASHBOARD -->
<div class="row">
    <!-- Widgets -->
    <div class="col-md-4 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">My Active Projects</h6>
                    <h3 class="mb-0 fw-bold text-primary"><?= $activeProjects ?></h3>
                </div>
                <div class="widget-icon text-primary bg-soft-primary rounded p-3">
                    <i class="bi bi-briefcase-fill"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">My Pending Tasks</h6>
                    <h3 class="mb-0 fw-bold text-warning"><?= $pendingTasks ?></h3>
                </div>
                <div class="widget-icon text-warning bg-soft-warning rounded p-3">
                    <i class="bi bi-list-task"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="card widget-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-bold mb-2">My Monthly Presence</h6>
                    <h3 class="mb-0 fw-bold text-success"><?= $monthPresence ?> <span class="fs-6 text-muted fw-normal">Days</span></h3>
                </div>
                <div class="widget-icon text-success bg-soft-success rounded p-3">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- My Tasks Table -->
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>My Tasks</span>
                <a href="tasks.php" class="btn btn-sm btn-outline-primary">Go to Tasks</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Task Name</th>
                                <th>Project</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myTasks)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">You have no pending tasks! Great job.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myTasks as $task): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= h($task['task_name']) ?></td>
                                    <td><?= h($task['project_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php 
                                            $isOverdue = strtotime($task['due_date']) < strtotime('today');
                                            $dateClass = $isOverdue ? 'text-danger fw-bold' : '';
                                        ?>
                                        <span class="<?= $dateClass ?>"><?= h($task['due_date'] ?: '-') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                            $statusClass = 'bg-soft-secondary';
                                            if ($task['status'] == 'In Progress') $statusClass = 'bg-soft-primary';
                                            if ($task['status'] == 'Review') $statusClass = 'bg-soft-warning';
                                        ?>
                                        <span class="badge badge-status <?= $statusClass ?>"><?= h($task['status']) ?></span>
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

    <!-- Upcoming Shoots Table -->
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>My Upcoming Shoots</span>
                <a href="projects.php" class="btn btn-sm btn-outline-primary">Go to Projects</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Project / Client</th>
                                <th>Shoot Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingShoots)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-4">No upcoming shoots assigned to you.</td></tr>
                            <?php else: ?>
                                <?php foreach ($upcomingShoots as $shoot): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= h($shoot['project_name']) ?></div>
                                        <div class="small text-muted"><?= h($shoot['client_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-primary fw-bold"><?= date('M d, Y', strtotime($shoot['shoot_date'])) ?></div>
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
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
