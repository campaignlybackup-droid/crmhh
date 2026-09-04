<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $completed = trim($_POST['completed_tasks'] ?? '');
    $pending = trim($_POST['pending_tasks'] ?? '');
    $blockers = trim($_POST['blockers'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Check if report exists for today
    $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    $stmt->execute([$user_id, $report_date]);
    
    if ($stmt->fetch()) {
        $upd = $pdo->prepare("UPDATE daily_reports SET completed_tasks=?, pending_tasks=?, blockers=?, notes=? WHERE user_id=? AND report_date=?");
        $upd->execute([$completed, $pending, $blockers, $notes, $user_id, $report_date]);
        $_SESSION['flash_success'] = "Daily report updated successfully!";
    } else {
        $ins = $pdo->prepare("INSERT INTO daily_reports (user_id, report_date, completed_tasks, pending_tasks, blockers, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$user_id, $report_date, $completed, $pending, $blockers, $notes]);
        $_SESSION['flash_success'] = "Daily report submitted successfully!";
    }
    
    logActivity($pdo, "Submitted Daily Report for $report_date", 'Report', $user_id);
    header("Location: daily_work.php");
    exit;
}

// Fetch Reports
$reportsSql = "SELECT r.*, u.username FROM daily_reports r JOIN users u ON r.user_id = u.id";
if (!$isFounder) {
    // Managers see their team's, users see only theirs
    $reportsSql .= " WHERE r.user_id IN ($visibleIdsStr)";
}
$reportsSql .= " ORDER BY r.report_date DESC, r.created_at DESC LIMIT 30";
$stmt = $pdo->query($reportsSql);
$reports = $stmt ? $stmt->fetchAll() : [];

// Check if current user submitted today
$todayReport = array_filter($reports, fn($r) => $r['user_id'] == $user_id && $r['report_date'] == date('Y-m-d'));
$todayReport = reset($todayReport);

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Daily Reports</h3>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#reportModal">
        <i class="bi bi-journal-plus me-2"></i><?= $todayReport ? 'Edit Today\'s Report' : 'Submit Report' ?>
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>User</th>
                        <th>Completed Tasks</th>
                        <th>Blockers</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No daily reports found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $rep): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= date('M d, Y', strtotime($rep['report_date'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-soft-primary text-primary d-flex justify-content-center align-items-center" style="width: 25px; height: 25px; font-weight: bold; font-size: 0.7rem;">
                                            <?= strtoupper(substr($rep['username'], 0, 1)) ?>
                                        </div>
                                        <?= h($rep['username']) ?>
                                    </div>
                                </td>
                                <td><div class="text-truncate" style="max-width: 200px;"><?= h($rep['completed_tasks'] ?: 'None') ?></div></td>
                                <td><div class="text-truncate text-danger" style="max-width: 150px;"><?= h($rep['blockers'] ?: 'None') ?></div></td>
                                <td class="pe-4 text-end">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#viewModal<?= $rep['id'] ?>">View</button>
                                </td>
                            </tr>
                            
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?= $rep['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header border-0 bg-light pb-2">
                                            <h5 class="modal-title fw-bold">Report: <?= h($rep['username']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <div class="small text-muted mb-3"><i class="bi bi-calendar me-2"></i><?= date('F d, Y', strtotime($rep['report_date'])) ?></div>
                                            
                                            <h6 class="fw-bold text-success mb-1">Completed Tasks</h6>
                                            <p class="small bg-soft-success p-2 rounded"><?= nl2br(h($rep['completed_tasks'] ?: '-')) ?></p>
                                            
                                            <h6 class="fw-bold text-warning mb-1">Pending Tasks</h6>
                                            <p class="small bg-soft-warning p-2 rounded"><?= nl2br(h($rep['pending_tasks'] ?: '-')) ?></p>
                                            
                                            <h6 class="fw-bold text-danger mb-1">Blockers/Issues</h6>
                                            <p class="small bg-soft-danger p-2 rounded"><?= nl2br(h($rep['blockers'] ?: '-')) ?></p>
                                            
                                            <h6 class="fw-bold text-primary mb-1">Notes</h6>
                                            <p class="small bg-soft-primary p-2 rounded"><?= nl2br(h($rep['notes'] ?: '-')) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Submit/Edit Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header border-0 bg-light pb-2">
                <h5 class="modal-title fw-bold"><?= $todayReport ? 'Edit Today\'s Report' : 'Submit Daily Report' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <input type="hidden" name="submit_report" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Report Date</label>
                    <input type="date" class="form-control" name="report_date" value="<?= date('Y-m-d') ?>" required <?= $todayReport ? 'readonly' : '' ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-success">Completed Tasks</label>
                    <textarea class="form-control" name="completed_tasks" rows="2" required><?= $todayReport ? h($todayReport['completed_tasks']) : '' ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-warning">Pending Tasks</label>
                    <textarea class="form-control" name="pending_tasks" rows="2"><?= $todayReport ? h($todayReport['pending_tasks']) : '' ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-danger">Problems / Blockers</label>
                    <textarea class="form-control" name="blockers" rows="2"><?= $todayReport ? h($todayReport['blockers']) : '' ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-primary">General Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?= $todayReport ? h($todayReport['notes']) : '' ?></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary fw-bold w-100">Save Report</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
