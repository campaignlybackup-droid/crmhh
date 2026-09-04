<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Handle Leave Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_date = $_POST['leave_date'];
    $reason = trim($_POST['reason']);
    
    $ins = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_date, reason) VALUES (?, ?, ?)");
    $ins->execute([$user_id, $leave_date, $reason]);
    
    logActivity($pdo, "Submitted Leave Request for $leave_date", 'Leave', $pdo->lastInsertId());
    $_SESSION['flash_success'] = "Leave request submitted successfully.";
    header("Location: hr.php");
    exit;
}

// Handle Leave Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave_status'])) {
    if ($isManager || $isFounder) {
        $leave_id = (int)$_POST['leave_id'];
        $new_status = $_POST['status']; // Approved, Rejected
        
        // Verify manager has access to this user
        $chk = $pdo->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
        $chk->execute([$leave_id]);
        $req_user = $chk->fetchColumn();
        
        if ($isFounder || in_array($req_user, $visibleIds)) {
            $upd = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ? WHERE id = ?");
            $upd->execute([$new_status, $user_id, $leave_id]);
            logActivity($pdo, "$new_status Leave Request ID $leave_id", 'Leave', $leave_id);
            $_SESSION['flash_success'] = "Leave request $new_status.";
        } else {
            $_SESSION['flash_error'] = "Unauthorized.";
        }
    }
    header("Location: hr.php");
    exit;
}

// Fetch Leaves
$leavesSql = "SELECT l.*, u.username, rv.username as reviewer 
              FROM leave_requests l 
              JOIN users u ON l.user_id = u.id 
              LEFT JOIN users rv ON l.reviewed_by = rv.id";
if (!$isFounder) {
    $leavesSql .= " WHERE l.user_id IN ($visibleIdsStr)";
}
$leavesSql .= " ORDER BY l.created_at DESC LIMIT 50";
$stmt = $pdo->query($leavesSql);
$leaves = $stmt ? $stmt->fetchAll() : [];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Leave Management</h3>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#leaveModal">
        <i class="bi bi-calendar2-minus me-2"></i> Request Leave
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
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No leave requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leaves as $l): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= date('M d, Y', strtotime($l['leave_date'])) ?></td>
                                <td><?= h($l['username']) ?></td>
                                <td><div class="text-truncate" style="max-width: 250px;"><?= h($l['reason']) ?></div></td>
                                <td>
                                    <?php 
                                    $bClass = ['Pending'=>'warning', 'Approved'=>'success', 'Rejected'=>'danger'][$l['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-soft-<?= $bClass ?> text-<?= $bClass ?> rounded-pill px-3"><?= h($l['status']) ?></span>
                                    <?php if ($l['reviewed_by']): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.65rem;">by <?= h($l['reviewer']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if (($isFounder || $isManager) && $l['status'] === 'Pending' && in_array($l['user_id'], $visibleIds)): ?>
                                        <form method="POST" class="d-inline m-0">
                                            <input type="hidden" name="update_leave_status" value="1">
                                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                            <button type="submit" name="status" value="Approved" class="btn btn-sm btn-success me-1" title="Approve"><i class="bi bi-check-lg"></i></button>
                                            <button type="submit" name="status" value="Rejected" class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border" disabled>No Action</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Request Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header border-0 bg-light pb-2">
                <h5 class="modal-title fw-bold">Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <input type="hidden" name="submit_leave" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Leave Date</label>
                    <input type="date" class="form-control" name="leave_date" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Reason</label>
                    <textarea class="form-control" name="reason" rows="3" required placeholder="Describe why you need the leave..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary fw-bold w-100">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
