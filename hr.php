<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$isManager = isManagerRole($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Handle Leave Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    $ins = $pdo->prepare("INSERT INTO leave_requests (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
    $ins->execute([$user_id, $start_date, $end_date, $reason]);
    
    logActivity($pdo, "Submitted Leave Request ($start_date to $end_date)", 'Leave', $pdo->lastInsertId());
    
    // Notify managers (optional, but good)
    $managersSql = "SELECT u.id FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.role_name IN ('Founder', 'Manager')";
    $managers = $pdo->query($managersSql)->fetchAll(PDO::FETCH_COLUMN);
    $myUsername = getCurrentUsername();
    foreach ($managers as $mgrId) {
        if ($mgrId != $user_id && ($isFounder || in_array($user_id, getVisibleUserIds($pdo, $mgrId)))) {
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, 'hr.php')")->execute([$mgrId, "$myUsername submitted a leave request."]);
        }
    }
    
    $_SESSION['flash_success'] = "Leave request submitted successfully.";
    header("Location: hr.php");
    exit;
}

// Handle Leave Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave_status'])) {
    if ($isManager || $isFounder) {
        $leave_id = (int)$_POST['leave_id'];
        $new_status = $_POST['status']; // Approved, Rejected
        
        $chk = $pdo->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
        $chk->execute([$leave_id]);
        $req_user = $chk->fetchColumn();
        
        // Strict Visibility Rule
        if ($isFounder || in_array($req_user, $visibleIds)) {
            $upd = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ? WHERE id = ?");
            $upd->execute([$new_status, $user_id, $leave_id]);
            logActivity($pdo, "$new_status Leave Request #$leave_id", 'Leave', $leave_id);
            
            // AUTOMATED NOTIFICATION INTEGRATION
            $msg = "Your leave request has been $new_status.";
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, 'hr.php')")->execute([$req_user, $msg]);
            
            $_SESSION['flash_success'] = "Leave request $new_status and employee notified.";
        } else {
            $_SESSION['flash_error'] = "403 Forbidden: You do not manage this user.";
        }
    }
    header("Location: hr.php");
    exit;
}

// Fetch My Leaves
$myLeavesSql = "SELECT l.*, rv.username as reviewer FROM leave_requests l LEFT JOIN users rv ON l.reviewed_by = rv.id WHERE l.user_id = ? ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($myLeavesSql);
$stmt->execute([$user_id]);
$myLeaves = $stmt->fetchAll();

// Fetch Team Leaves (for Managers/Founders)
$teamLeaves = [];
if ($isFounder || $isManager) {
    $teamLeavesSql = "SELECT l.*, u.username as requester, rv.username as reviewer FROM leave_requests l JOIN users u ON l.user_id = u.id LEFT JOIN users rv ON l.reviewed_by = rv.id WHERE l.user_id != ?";
    if (!$isFounder) {
        $teamLeavesSql .= " AND l.user_id IN ($visibleIdsStr)";
    }
    $teamLeavesSql .= " ORDER BY (CASE WHEN l.status = 'Pending' THEN 1 ELSE 2 END), l.created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($teamLeavesSql);
    $stmt->execute([$user_id]);
    $teamLeaves = $stmt->fetchAll();
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR & Leaves - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">HR & Leave Management</h2>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#requestLeaveModal">
                <i class="bi bi-calendar2-plus"></i> Request Time Off
            </button>
        </div>

        <div class="row g-4">
            
            <?php if ($isFounder || $isManager): ?>
            <!-- Manager Queue -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 border-top border-warning border-4 mb-2">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold text-dark"><i class="bi bi-inboxes"></i> Team Leave Requests (Manager Queue)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teamLeaves)): ?>
                            <p class="text-muted text-center py-3">No pending leave requests from your squad.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Dates</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($teamLeaves as $leave): ?>
                                        <tr class="<?= $leave['status'] == 'Pending' ? 'table-warning' : '' ?>">
                                            <td class="fw-bold"><i class="bi bi-person text-muted"></i> <?= h($leave['requester']) ?></td>
                                            <td>
                                                <span class="fw-bold text-dark"><?= date('M j', strtotime($leave['start_date'])) ?></span> to 
                                                <span class="fw-bold text-dark"><?= date('M j, Y', strtotime($leave['end_date'])) ?></span>
                                            </td>
                                            <td class="text-muted small"><?= h($leave['reason']) ?></td>
                                            <td>
                                                <?php 
                                                $badge = 'bg-secondary';
                                                if ($leave['status'] == 'Approved') $badge = 'bg-success';
                                                if ($leave['status'] == 'Rejected') $badge = 'bg-danger';
                                                if ($leave['status'] == 'Pending') $badge = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?= $badge ?>"><?= h($leave['status']) ?></span>
                                                <?php if ($leave['reviewed_by']): ?>
                                                <div class="small text-muted mt-1" style="font-size:0.7rem;">by <?= h($leave['reviewer']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($leave['status'] == 'Pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="update_leave_status" value="1">
                                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                    <input type="hidden" name="status" value="Approved">
                                                    <button type="submit" class="btn btn-sm btn-success fw-bold me-1"><i class="bi bi-check-lg"></i> Approve</button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="update_leave_status" value="1">
                                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                    <input type="hidden" name="status" value="Rejected">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-x-lg"></i> Reject</button>
                                                </form>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-check-circle"></i> Resolved</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- My Leaves -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold text-primary"><i class="bi bi-clock-history"></i> My Leave History</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($myLeaves)): ?>
                            <p class="text-muted text-center py-4">You have not requested any time off yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Requested On</th>
                                            <th>Dates</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($myLeaves as $leave): ?>
                                        <tr>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($leave['created_at'])) ?></td>
                                            <td>
                                                <span class="fw-bold text-dark"><?= date('M j', strtotime($leave['start_date'])) ?></span> to 
                                                <span class="fw-bold text-dark"><?= date('M j, Y', strtotime($leave['end_date'])) ?></span>
                                            </td>
                                            <td class="text-muted small"><?= h($leave['reason']) ?></td>
                                            <td>
                                                <?php 
                                                $badge = 'bg-secondary';
                                                if ($leave['status'] == 'Approved') $badge = 'bg-success';
                                                if ($leave['status'] == 'Rejected') $badge = 'bg-danger';
                                                if ($leave['status'] == 'Pending') $badge = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?= $badge ?>"><?= h($leave['status']) ?></span>
                                                <?php if ($leave['status'] != 'Pending' && $leave['reviewer']): ?>
                                                    <div class="small text-muted mt-1" style="font-size:0.7rem;">by <?= h($leave['reviewer']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="requestLeaveModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Request Time Off</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Reason</label>
            <textarea name="reason" class="form-control" rows="3" required placeholder="e.g., Vacation, Medical Appointment..."></textarea>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="submit_leave" class="btn btn-primary fw-bold">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
