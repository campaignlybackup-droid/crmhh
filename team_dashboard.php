<?php
require_once 'functions.php';
requireLogin();

// Superadmin only
if (!isSuperAdmin()) {
    $_SESSION['flash_error'] = "Unauthorized access.";
    header("Location: dashboard.php");
    exit;
}

$view_user_id = $_GET['user_id'] ?? null;
$current_month = date('m');
$current_year = date('Y');

if ($view_user_id) {
    try {
        // Detailed view for a specific user
        $stmt = $pdo->prepare("SELECT username, designation, department FROM users WHERE id = ?");
        $stmt->execute([$view_user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['flash_error'] = "User not found.";
            header("Location: team_dashboard.php");
            exit;
        }
        
        // Get work logs for this month
        $stmt = $pdo->prepare("SELECT * FROM daily_work WHERE user_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ? ORDER BY work_date DESC");
        $stmt->execute([$view_user_id, $current_month, $current_year]);
        $work_logs = $stmt->fetchAll();
        
        // Get ongoing tasks
        $stmt = $pdo->prepare("SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? AND t.status = 'In Progress'");
        $stmt->execute([$view_user_id]);
        $ongoing_tasks = $stmt->fetchAll();
    } catch (PDOException $e) {
        $user = ['username' => 'Unknown', 'designation' => '', 'department' => ''];
        $work_logs = $ongoing_tasks = [];
    }
} else {
    try {
        // Overview for all users
        $users = $pdo->query("SELECT id, username, designation, department FROM users WHERE role != 'superadmin' ORDER BY username ASC")->fetchAll();
        
        $user_stats = [];
        foreach ($users as $u) {
            $uid = $u['id'];
            
            // Count monthly presence (distinct work dates this month)
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT work_date) FROM daily_work WHERE user_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ?");
            $stmt->execute([$uid, $current_month, $current_year]);
            $presence_count = $stmt->fetchColumn() ?: 0;
            
            // Ongoing tasks
            $stmt = $pdo->prepare("SELECT task_name, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? AND t.status = 'In Progress'");
            $stmt->execute([$uid]);
            $tasks = $stmt->fetchAll();
            
            $user_stats[] = [
                'id' => $uid,
                'username' => $u['username'],
                'designation' => $u['designation'],
                'department' => $u['department'],
                'presence' => $presence_count,
                'tasks' => $tasks
            ];
        }
    } catch (PDOException $e) {
        $user_stats = [];
    }
}

include 'header.php';
?>

<?php if ($view_user_id && $user): ?>
    <!-- User Detail View -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h3 class="fw-bold mb-0">Dashboard: <?= h($user['username']) ?></h3>
            <div class="text-muted mb-2"><?= h($user['designation'] ?: 'Employee') ?> &bull; <?= h($user['department'] ?: 'General') ?></div>
            <p class="text-muted mb-0">Monthly Presence (<?= date('F Y') ?>) & Real-time Tasks</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="team_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Team</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">Ongoing Tasks (In Progress)</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if(empty($ongoing_tasks)): ?>
                            <li class="list-group-item text-muted py-3">No ongoing tasks.</li>
                        <?php else: ?>
                            <?php foreach($ongoing_tasks as $t): ?>
                                <li class="list-group-item py-3">
                                    <div class="fw-bold"><?= h($t['task_name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-folder2"></i> <?= h($t['project_name'] ?? 'General') ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    Daily Work Logs (<?= date('F Y') ?>)
                    <span class="badge bg-primary rounded-pill"><?= count($work_logs) ?> Days Present</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3" style="width: 150px;">Date</th>
                                    <th>Work Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($work_logs)): ?>
                                    <tr><td colspan="2" class="text-center py-4 text-muted">No work logged this month.</td></tr>
                                <?php else: ?>
                                    <?php foreach($work_logs as $log): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?= h(date('M d, Y', strtotime($log['work_date']))) ?></td>
                                        <td><?= nl2br(h($log['description'])) ?></td>
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
    <!-- Overview -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-12">
            <h3 class="fw-bold mb-0">Team Dashboard</h3>
            <p class="text-muted mb-0">Overview of user presence and ongoing tasks for <?= date('F Y') ?>.</p>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if(empty($user_stats)): ?>
            <div class="col-12"><div class="alert alert-info">No team members found.</div></div>
        <?php else: ?>
            <?php foreach($user_stats as $stat): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 kanban-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0 fw-bold d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2" style="width: 35px; height: 35px; font-size: 1rem;">
                                    <?= strtoupper(substr($stat['username'], 0, 1)) ?>
                                </div>
                                <?= h($stat['username']) ?>
                            </h5>
                            <span class="badge bg-success" title="Days Present This Month">
                                <i class="bi bi-calendar-check"></i> <?= $stat['presence'] ?> Days
                            </span>
                        </div>
                        
                        <div class="small text-muted mb-3 border-bottom pb-2">
                            <strong><?= h($stat['department'] ?: 'General') ?></strong> &bull; <?= h($stat['designation'] ?: 'Employee') ?>
                        </div>
                        
                        <div class="flex-grow-1">
                            <h6 class="text-muted small fw-bold mb-2">ONGOING TASKS</h6>
                            <?php if(empty($stat['tasks'])): ?>
                                <div class="text-muted small mb-2 fst-italic">None</div>
                            <?php else: ?>
                                <ul class="list-unstyled small">
                                    <?php foreach(array_slice($stat['tasks'], 0, 3) as $t): ?>
                                        <li class="mb-1 text-truncate" title="<?= h($t['task_name']) ?>">
                                            <i class="bi bi-check2 text-primary"></i> <?= h($t['task_name']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if(count($stat['tasks']) > 3): ?>
                                        <li class="text-muted fst-italic">+<?= count($stat['tasks']) - 3 ?> more</li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top">
                            <a href="?user_id=<?= $stat['id'] ?>" class="btn btn-sm btn-outline-primary w-100">View Detailed Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
