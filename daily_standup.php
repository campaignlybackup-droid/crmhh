<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$isManager = isManagerRole($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Check if user has already submitted a standup today
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT id FROM daily_standups WHERE user_id = ? AND created_at = ?");
$stmt->execute([$user_id, $today]);
$hasSubmittedToday = (bool)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_standup'])) {
    if (!$hasSubmittedToday) {
        $yesterday = $_POST['yesterday_work'];
        $today_plan = $_POST['today_plan'];
        $blockers = $_POST['blockers'];
        
        $pdo->prepare("INSERT INTO daily_standups (user_id, yesterday_work, today_plan, blockers, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$user_id, $yesterday, $today_plan, $blockers, $today]);
        
        logActivity($pdo, "Submitted Daily Standup", 'User', $user_id);
        $_SESSION['flash_success'] = "Standup submitted successfully. Great work!";
        header("Location: daily_standup.php");
        exit;
    }
}

// Fetch team standups (for Founders/Managers)
$teamStandups = [];
$dateFilter = $_GET['date'] ?? $today;

if ($isFounder || $isManager) {
    $feedSql = "SELECT s.*, u.username FROM daily_standups s JOIN users u ON s.user_id = u.id WHERE s.created_at = ?";
    if (!$isFounder) {
        $feedSql .= " AND s.user_id IN ($visibleIdsStr)";
    }
    $feedSql .= " ORDER BY s.id DESC";
    $stmt = $pdo->prepare($feedSql);
    $stmt->execute([$dateFilter]);
    $teamStandups = $stmt->fetchAll();
}

$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Standup - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Daily Standup</h2>
        </div>

        <div class="row g-4">
            <!-- Employee Submission Form -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold text-primary"><i class="bi bi-pencil-square"></i> My Standup (<?= date('M j, Y') ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($hasSubmittedToday): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 fw-bold">All Set!</h4>
                                <p class="text-muted">You have already submitted your standup for today.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">What did you accomplish yesterday?</label>
                                    <textarea name="yesterday_work" class="form-control" rows="3" required placeholder="e.g., Completed 3 lead calls, finished the XYZ deliverable..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">What is your plan for today?</label>
                                    <textarea name="today_plan" class="form-control" rows="3" required placeholder="e.g., Focus on onboarding the new client..."></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-muted">Any blockers?</label>
                                    <textarea name="blockers" class="form-control" rows="2" placeholder="e.g., Waiting on assets from design team... (leave blank if none)"></textarea>
                                </div>
                                <button type="submit" name="submit_standup" class="btn btn-primary fw-bold w-100 py-2">Submit Standup</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Manager/Founder Feed -->
            <?php if ($isFounder || $isManager): ?>
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark"><i class="bi bi-people-fill"></i> Team Feed</h5>
                        <form method="GET" class="d-flex align-items-center">
                            <input type="date" name="date" class="form-control form-control-sm me-2" value="<?= h($dateFilter) ?>" max="<?= date('Y-m-d') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">View</button>
                        </form>
                    </div>
                    <div class="card-body bg-light p-4" style="max-height: 600px; overflow-y: auto;">
                        <?php if(empty($teamStandups)): ?>
                            <p class="text-muted text-center py-4">No standups submitted on <?= date('M j, Y', strtotime($dateFilter)) ?>.</p>
                        <?php else: ?>
                            <?php foreach($teamStandups as $s): ?>
                                <div class="card border-0 shadow-sm rounded-3 mb-3">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($s['username'], 0, 1)) ?>
                                            </div>
                                            <?= h($s['username']) ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-sm-6 mb-2">
                                                <div class="small text-muted fw-bold mb-1"><i class="bi bi-arrow-left-circle text-secondary"></i> Yesterday</div>
                                                <p class="small mb-0"><?= nl2br(h($s['yesterday_work'])) ?></p>
                                            </div>
                                            <div class="col-sm-6 mb-2">
                                                <div class="small text-muted fw-bold mb-1"><i class="bi bi-arrow-right-circle text-success"></i> Today</div>
                                                <p class="small mb-0"><?= nl2br(h($s['today_plan'])) ?></p>
                                            </div>
                                        </div>
                                        <?php if (!empty(trim($s['blockers']))): ?>
                                            <div class="mt-2 pt-2 border-top border-danger border-opacity-25">
                                                <div class="small text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Blockers</div>
                                                <p class="small mb-0 text-danger"><?= nl2br(h($s['blockers'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
