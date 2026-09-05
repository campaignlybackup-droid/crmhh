<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$isManager = isManagerRole($pdo, $user_id);

$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);
$hasFilter = !empty($visibleIdsStr);

// Labels
if ($isFounder) {
    $revLabel = "Global Revenue";
    $convLabel = "Agency Conversion";
    $taskLabel = "Agency Pending Tasks";
    $boardLabel = "Top 5 Agency Leaderboard";
} elseif ($isManager) {
    $revLabel = "Team Revenue";
    $convLabel = "Team Conversion";
    $taskLabel = "Team Pending Tasks";
    $boardLabel = "Subordinate Leaderboard";
} else {
    $revLabel = "Personal Deal Value";
    $convLabel = "My Conversion Rate";
    $taskLabel = "My Pending Tasks";
    $boardLabel = "My Performance Snapshot";
}

// Metrics
$revenue = 0;
$conversionRate = 0;
$pendingTasksCount = 0;
$leaderboard = [];
$recentActivities = []; // We can pull from audit_logs

try {
    // KPI: Revenue Generated (From Won Leads)
    $revSql = "SELECT COALESCE(SUM(deal_value), 0) FROM leads WHERE status = 'Won' AND deleted_at IS NULL";
    if ($hasFilter) {
        $revSql .= " AND assigned_to IN ($visibleIdsStr)";
    }
    $revenue = $pdo->query($revSql)->fetchColumn() ?: 0;

    // KPI: Conversion Rate
    $leadCond = "WHERE deleted_at IS NULL";
    if ($hasFilter) {
        $leadCond .= " AND assigned_to IN ($visibleIdsStr)";
    }
    $totalLeads = $pdo->query("SELECT COUNT(*) FROM leads $leadCond")->fetchColumn();
    $wonLeads = $pdo->query("SELECT COUNT(*) FROM leads $leadCond AND status = 'Won'")->fetchColumn();
    $conversionRate = ($totalLeads > 0) ? round(($wonLeads / $totalLeads) * 100, 1) : 0;

    // KPI: Pending Tasks
    $taskSql = "SELECT COUNT(*) FROM tasks WHERE status NOT IN ('Completed', 'Cancelled') AND deleted_at IS NULL";
    if ($hasFilter) {
        $taskSql .= " AND assigned_to IN ($visibleIdsStr)";
    }
    $pendingTasksCount = $pdo->query($taskSql)->fetchColumn();

    // Leaderboard
    $boardSql = "
        SELECT u.id, u.username,
               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'Completed' AND t.deleted_at IS NULL) as completed_tasks,
               (SELECT COALESCE(SUM(deal_value), 0) FROM leads l WHERE l.assigned_to = u.id AND l.status = 'Won' AND l.deleted_at IS NULL) as won_revenue
        FROM users u
        WHERE u.status = 'active' AND u.deleted_at IS NULL
    ";
    if ($hasFilter) {
        $boardSql .= " AND u.id IN ($visibleIdsStr)";
    }
    $boardSql .= " ORDER BY won_revenue DESC, completed_tasks DESC";
    if ($isFounder) {
        $boardSql .= " LIMIT 5";
    }
    $leaderboard = $pdo->query($boardSql)->fetchAll();

    // Recent Audit Logs
    $auditSql = "SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id";
    if ($hasFilter) {
        $auditSql .= " WHERE a.user_id IN ($visibleIdsStr)";
    }
    $auditSql .= " ORDER BY a.timestamp DESC LIMIT 5";
    $recentActivities = $pdo->query($auditSql)->fetchAll();

} catch (PDOException $e) {
    // Safe failure if tables are missing or not setup properly yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .kpi-card { transition: transform 0.2s ease-in-out; border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.04); }
        .kpi-card:hover { transform: translateY(-5px); }
        .icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Welcome back, <?= h(getCurrentUsername()) ?></h2>
        </div>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"><?= h($revLabel) ?></h6>
                                <h3 class="fw-bold mb-0 text-success">$<?= number_format($revenue, 2) ?></h3>
                            </div>
                            <div class="icon-circle bg-success bg-opacity-10 text-success">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"><?= h($convLabel) ?></h6>
                                <h3 class="fw-bold mb-0 text-primary"><?= h($conversionRate) ?>%</h3>
                            </div>
                            <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"><?= h($taskLabel) ?></h6>
                                <h3 class="fw-bold mb-0 text-warning"><?= h($pendingTasksCount) ?></h3>
                            </div>
                            <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-list-task"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Leaderboard -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0"><?= h($boardLabel) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leaderboard)): ?>
                            <p class="text-muted text-center py-4">No data available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr class="text-secondary">
                                            <th>Rank</th>
                                            <th>User</th>
                                            <th>Won Revenue</th>
                                            <th>Completed Tasks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rank = 1; foreach ($leaderboard as $member): ?>
                                        <tr>
                                            <td>
                                                <?php if($rank == 1): ?>
                                                    <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-trophy-fill"></i> 1st</span>
                                                <?php elseif($rank == 2): ?>
                                                    <span class="badge bg-secondary px-2 py-1">2nd</span>
                                                <?php elseif($rank == 3): ?>
                                                    <span class="badge bg-dark px-2 py-1">3rd</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark px-2 py-1"><?= $rank ?>th</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold"><?= h($member['username']) ?></td>
                                            <td class="text-success fw-bold">$<?= number_format($member['won_revenue'], 2) ?></td>
                                            <td><span class="badge bg-info-subtle text-info fw-bold"><?= $member['completed_tasks'] ?> Tasks</span></td>
                                        </tr>
                                        <?php $rank++; endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">Recent Activity Log</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-muted text-center py-4">No recent activities.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="d-flex mb-3">
                                        <div class="me-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-activity"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-sm"><strong><?= h($activity['username'] ?? 'System') ?></strong> <?= h($activity['action']) ?> on <strong><?= h($activity['entity_type']) ?> #<?= h($activity['entity_id']) ?></strong></p>
                                            <small class="text-muted"><?= h(date('M j, g:i a', strtotime($activity['timestamp']))) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
