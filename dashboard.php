<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();
$db_error = false;

// Initialize Widget Data
$pendingTasks = 0;
$meetingsToday = 0;
$approvalsWaiting = 0;
$attentionProjects = 0;

$agendaItems = [];
$activityLog = [];
$upcomingDeadlines = [];
$totalRevenueDashboard = 0;

$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = implode(',', $visibleIds);

try {
    // 1. Pending Tasks
    $tasksSql = "SELECT COUNT(*) FROM tasks WHERE status != 'Done'";
    if (!$isSuper) $tasksSql .= " AND assigned_to IN ($visibleIdsStr)";
    $pendingTasks = $pdo->query($tasksSql)->fetchColumn() ?: 0;

    // 2. Meetings Today
    $meetingsSql = "SELECT COUNT(*) FROM meetings WHERE DATE(start_time) = CURDATE()";
    // We don't have participants mapped yet, so we'll just show all meetings for now.
    $meetingsToday = $pdo->query($meetingsSql)->fetchColumn() ?: 0;

    // 3. Approvals Waiting
    $approvalsSql = "SELECT COUNT(*) FROM approvals WHERE status = 'Pending'";
    if (!$isSuper) $approvalsSql .= " AND approver_id IN ($visibleIdsStr)";
    $approvalsWaiting = $pdo->query($approvalsSql)->fetchColumn() ?: 0;

    // 4. Projects Needing Attention
    $attentionSql = "SELECT COUNT(*) FROM projects WHERE status IN ('Briefing', 'Review')";
    if (!$isSuper) $attentionSql .= " AND assigned_to IN ($visibleIdsStr)";
    $attentionProjects = $pdo->query($attentionSql)->fetchColumn() ?: 0;


    // --- 3 COLUMN DATA ---

    // Column 1: Today's Agenda (Tasks due today, Meetings today)
    $tasksTodaySql = "SELECT id, 'Task' as type, task_name as title, due_date as time_info FROM tasks WHERE due_date = CURDATE() AND status != 'Done'";
    if (!$isSuper) $tasksTodaySql .= " AND assigned_to IN ($visibleIdsStr)";
    
    $meetingsTodaySql = "SELECT id, 'Meeting' as type, title, TIME_FORMAT(start_time, '%H:%i') as time_info FROM meetings WHERE DATE(start_time) = CURDATE()";
    
    // Combine them (Union requires same columns)
    $agendaQuery = $pdo->query("$tasksTodaySql UNION $meetingsTodaySql ORDER BY time_info ASC LIMIT 10");
    if ($agendaQuery) {
        $agendaItems = $agendaQuery->fetchAll();
    }

    // Column 2: Recent Activity
    $activitySql = "SELECT a.*, u.username FROM activity_log a LEFT JOIN users u ON a.user_id = u.id ";
    if (!$isSuper) {
        // Since we don't have perfect entity matching, we show user's own activity for now
        $activitySql .= " WHERE a.user_id IN ($visibleIdsStr) ";
    }
    $activitySql .= " ORDER BY a.created_at DESC LIMIT 8";
    
    $activityStmt = $pdo->query($activitySql);
    if ($activityStmt) {
        $activityLog = $activityStmt->fetchAll();
    }

    // Column 3: Upcoming Deadlines (Next 7 days)
    $deadlinesSql = "SELECT id, project_name as title, delivery_date as deadline FROM projects WHERE delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status != 'Delivered'";
    if (!$isSuper) $deadlinesSql .= " AND assigned_to IN ($visibleIdsStr)";
    $deadlinesSql .= " ORDER BY delivery_date ASC LIMIT 5";
    
    $deadlinesStmt = $pdo->query($deadlinesSql);
    if ($deadlinesStmt) {
        $upcomingDeadlines = $deadlinesStmt->fetchAll();
    }

    // Financial Snippet
    if ($isSuper) {
        $invQuery = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'Paid'");
        if ($invQuery) {
            $totalRevenueDashboard = $invQuery->fetchColumn() ?: 0;
        }
    }

} catch (\Throwable $e) {
    $db_error = true;
    // Catch errors silently if tables don't exist yet
}

include 'header.php';
?>

<!-- Quick Actions -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">What do I have today?</h3>
        <p class="text-muted mb-0">Here's your summary, <?= h(getCurrentUsername()) ?>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="tasks.php" class="btn btn-primary shadow-sm"><i class="bi bi-check2-square me-2"></i>New Task</a>
        <?php if ($isSuper): ?>
        <a href="projects.php" class="btn btn-dark shadow-sm"><i class="bi bi-briefcase me-2"></i>New Project</a>
        <?php endif; ?>
    </div>
</div>

<!-- Widgets Row -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-warning text-warning me-3">
                    <i class="bi bi-list-task"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $pendingTasks ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Pending Tasks</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-primary text-primary me-3">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $meetingsToday ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Meetings Today</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-success text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $approvalsWaiting ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Approvals Waiting</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-danger text-danger me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $attentionProjects ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Projects Need Attention</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row g-4">
    <!-- Column 1: Agenda -->
    <div class="col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Today's Agenda</h5>
                <i class="bi bi-calendar-date text-muted"></i>
            </div>
            <div class="card-body">
                <?php if (empty($agendaItems)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-cup-hot fs-1 d-block mb-3"></i>
                        Nothing on the agenda for today.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach($agendaItems as $item): ?>
                            <div class="d-flex align-items-start p-3 rounded bg-secondary bg-opacity-10">
                                <?php if($item['type'] == 'Meeting'): ?>
                                    <div class="text-primary me-3 mt-1"><i class="bi bi-camera-video-fill fs-5"></i></div>
                                <?php else: ?>
                                    <div class="text-warning me-3 mt-1"><i class="bi bi-check2-square fs-5"></i></div>
                                <?php endif; ?>
                                <div>
                                    <?php if($item['type'] == 'Meeting'): ?>
                                        <div class="fw-bold text-body"><?= h($item['title']) ?></div>
                                    <?php else: ?>
                                        <a href="task_view.php?id=<?= $item['id'] ?>" class="fw-bold text-body text-decoration-none hover-primary"><?= h($item['title']) ?></a>
                                    <?php endif; ?>
                                    <div class="small text-muted"><i class="bi bi-clock me-1"></i> <?= h($item['time_info']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Column 2: Recent Activity -->
    <div class="col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-0 pb-0">
                <h5 class="fw-bold mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($activityLog)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-activity fs-1 d-block mb-3"></i>
                        No recent activity found.
                    </div>
                <?php else: ?>
                    <div class="position-relative">
                        <?php foreach($activityLog as $log): ?>
                            <div class="d-flex mb-4 position-relative">
                                <div class="me-3">
                                    <div class="rounded-circle bg-soft-primary text-primary d-flex justify-content-center align-items-center" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.8rem;">
                                        <?= strtoupper(substr($log['username'] ?? '?', 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small fw-bold text-body mb-1">
                                        <?= h($log['action_type'] ?? 'Action') ?>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        <strong><?= h($log['username'] ?? 'System') ?></strong> on <?= h($log['entity_type']) ?>
                                    </div>
                                    <?php if ($log['details']): ?>
                                        <div class="small p-2 bg-secondary bg-opacity-10 rounded text-body-secondary mt-1 border">
                                            <?= h($log['details']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted mt-2" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1"></i> <?= date('M d, g:i A', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="mt-3 text-center">
                    <a href="activity.php" class="btn btn-sm btn-outline-primary w-100">View All Activity</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Column 3: Action Items -->
    <div class="col-lg-4">
        <div class="d-flex flex-column h-100 gap-4">
            
            <!-- Upcoming Deadlines -->
            <div class="card border-0 shadow-sm flex-grow-1">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Upcoming Deadlines</h5>
                    <span class="badge bg-soft-danger text-danger">Next 7 Days</span>
                </div>
                <div class="card-body">
                    <?php if(empty($upcomingDeadlines)): ?>
                        <div class="text-center text-muted py-3 small">
                            No upcoming deadlines!
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach($upcomingDeadlines as $dl): ?>
                                <li class="list-group-item px-0 py-3 bg-transparent d-flex justify-content-between align-items-center border-bottom-0 border-top">
                                    <a href="project_view.php?id=<?= $dl['id'] ?>" class="fw-semibold text-body text-truncate pe-2 text-decoration-none hover-primary"><?= h($dl['title']) ?></a>
                                    <div class="badge bg-soft-danger text-danger fw-bold rounded-pill">
                                        <?= date('M d', strtotime($dl['deadline'])) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications (Mini) -->
            <div class="card border-0 shadow-sm flex-grow-1">
                <div class="card-header border-0 pb-0">
                    <h5 class="fw-bold mb-0">New Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center text-muted py-3 small">
                            No new notifications.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php 
                                $miniNotifs = array_slice($notifications, 0, 3);
                                foreach($miniNotifs as $n): 
                            ?>
                                <li class="list-group-item px-0 py-2 bg-transparent border-bottom-0 border-top">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-dot text-primary fs-4 mt-n1"></i>
                                        <div>
                                            <div class="small text-body"><?= h($n['message']) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?= h($n['created_at']) ?></div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
