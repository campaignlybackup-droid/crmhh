<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$isManager = ($_SESSION['role'] === 'manager' || $isSuper);
$user_id = getCurrentUserId();
$today = date('Y-m-d');

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Attendance
    if ($action === 'punch_in') {
        $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $check->execute([$user_id, $today]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in) VALUES (?, ?, CURRENT_TIME)");
            $ins->execute([$user_id, $today]);
            $_SESSION['flash_success'] = "Punched In Successfully.";
        }
        header("Location: hr.php");
        exit;
    }
    elseif ($action === 'punch_out') {
        $check = $pdo->prepare("SELECT id, clock_in FROM attendance WHERE user_id = ? AND date = ?");
        $check->execute([$user_id, $today]);
        $row = $check->fetch();
        if ($row && !$row['clock_out']) {
            $upd = $pdo->prepare("UPDATE attendance SET clock_out = CURRENT_TIME, total_hours = ROUND(TIME_TO_SEC(TIMEDIFF(CURRENT_TIME, clock_in))/3600, 2) WHERE id = ?");
            $upd->execute([$row['id']]);
            $_SESSION['flash_success'] = "Punched Out Successfully.";
        }
        header("Location: hr.php");
        exit;
    }
    
    // Leaves
    elseif ($action === 'request_leave') {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $type = $_POST['type'];
        $reason = $_POST['reason'];
        
        $ins = $pdo->prepare("INSERT INTO leave_requests (user_id, start_date, end_date, type, reason) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$user_id, $start_date, $end_date, $type, $reason]);
        $_SESSION['flash_success'] = "Leave Request Submitted.";
        header("Location: hr.php");
        exit;
    }
    elseif ($action === 'approve_leave' && $isManager) {
        $leave_id = $_POST['leave_id'];
        $status = $_POST['status']; // 'Manager Approved', 'Admin Approved', 'Rejected'
        
        $upd = $pdo->prepare("UPDATE leave_requests SET status = ?, admin_id = ? WHERE id = ?");
        $upd->execute([$status, $user_id, $leave_id]);
        
        // Sync to Calendar if fully approved
        if (strpos($status, 'Approved') !== false) {
            $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
            $stmt->execute([$leave_id]);
            $lr = $stmt->fetch();
            
            $insEvt = $pdo->prepare("INSERT INTO calendar_events (user_id, title, start_time, end_time, type, reference_id) VALUES (?, ?, ?, ?, 'Leave', ?)");
            $insEvt->execute([$lr['user_id'], $lr['type'] . ' Leave', $lr['start_date'] . ' 00:00:00', $lr['end_date'] . ' 23:59:59', $leave_id]);
        }
        
        $_SESSION['flash_success'] = "Leave Request updated to $status.";
        header("Location: hr.php");
        exit;
    }
    
    // Holidays
    elseif ($action === 'add_holiday' && $isSuper) {
        $title = $_POST['title'];
        $date = $_POST['date'];
        
        $ins = $pdo->prepare("INSERT INTO company_holidays (title, date) VALUES (?, ?)");
        $ins->execute([$title, $date]);
        $hol_id = $pdo->lastInsertId();
        
        // Sync to Calendar
        $insEvt = $pdo->prepare("INSERT INTO calendar_events (title, start_time, end_time, type, reference_id) VALUES (?, ?, ?, 'Holiday', ?)");
        $insEvt->execute([$title, $date . ' 00:00:00', $date . ' 23:59:59', $hol_id]);
        
        $_SESSION['flash_success'] = "Holiday Added.";
        header("Location: hr.php");
        exit;
    }
    elseif ($action === 'delete_holiday' && $isSuper) {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM company_holidays WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM calendar_events WHERE type='Holiday' AND reference_id=?")->execute([$id]);
        $_SESSION['flash_success'] = "Holiday Deleted.";
        header("Location: hr.php");
        exit;
    }
}

// --- FETCH DATA ---
$today_attendance = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$today_attendance->execute([$user_id, $today]);
$my_today = $today_attendance->fetch();

$my_leaves = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
$my_leaves->execute([$user_id]);
$my_leaves = $my_leaves->fetchAll();

$holidays = $pdo->query("SELECT * FROM company_holidays ORDER BY date ASC")->fetchAll();

$team_leaves = [];
$team_attendance = [];
if ($isManager) {
    $team_leaves = $pdo->query("SELECT lr.*, u.username FROM leave_requests lr JOIN users u ON lr.user_id = u.id ORDER BY lr.created_at DESC")->fetchAll();
    
    // Today's team attendance
    $team_attendance = $pdo->prepare("SELECT a.*, u.username FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ? ORDER BY a.clock_in ASC");
    $team_attendance->execute([$today]);
    $team_attendance = $team_attendance->fetchAll();
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">HR & Timesheets</h3>
</div>

<div class="row g-4">
    <!-- Left Column: Attendance & Leaves -->
    <div class="col-lg-8">
        
        <!-- My Attendance -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light border-0">
                <h6 class="fw-bold mb-0">My Attendance <span class="fw-normal text-muted ms-2">(<?= date('D, M j, Y') ?>)</span></h6>
            </div>
            <div class="card-body p-4 text-center">
                <?php if (!$my_today): ?>
                    <i class="bi bi-clock-history fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="mb-3">You haven't punched in today.</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="punch_in">
                        <button type="submit" class="btn btn-lg btn-success rounded-pill px-5 shadow-sm"><i class="bi bi-box-arrow-in-right me-2"></i> Punch In</button>
                    </form>
                <?php elseif ($my_today && !$my_today['clock_out']): ?>
                    <i class="bi bi-stopwatch fs-1 text-primary mb-3 d-block"></i>
                    <h5 class="mb-2">You are punched in!</h5>
                    <p class="text-muted small mb-4">Punched in at: <strong class="text-dark"><?= date('g:i A', strtotime($my_today['clock_in'])) ?></strong></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="punch_out">
                        <button type="submit" class="btn btn-lg btn-danger rounded-pill px-5 shadow-sm"><i class="bi bi-box-arrow-left me-2"></i> Punch Out</button>
                    </form>
                <?php else: ?>
                    <i class="bi bi-check2-circle fs-1 text-success mb-3 d-block"></i>
                    <h5 class="mb-2">Shift Completed</h5>
                    <p class="text-muted small mb-0">
                        In: <strong><?= date('g:i A', strtotime($my_today['clock_in'])) ?></strong> | 
                        Out: <strong><?= date('g:i A', strtotime($my_today['clock_out'])) ?></strong>
                    </p>
                    <h4 class="mt-3 text-primary"><?= h($my_today['total_hours']) ?> Hours</h4>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Leaves -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">My Leave Requests</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">Request Leave</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Type</th>
                                <th>Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($my_leaves)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No leave requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach($my_leaves as $l): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted small"><?= h($l['type'] ?? 'Casual') ?></td>
                                    <td><?= date('M j', strtotime($l['start_date'])) ?> - <?= date('M j, Y', strtotime($l['end_date'])) ?></td>
                                    <td class="text-truncate" style="max-width: 150px;" title="<?= h($l['reason']) ?>"><?= h($l['reason']) ?></td>
                                    <td>
                                        <?php
                                            $badge = 'bg-secondary';
                                            if (strpos($l['status'], 'Approved') !== false) $badge = 'bg-success';
                                            if ($l['status'] == 'Rejected') $badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= h($l['status']) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($isManager): ?>
        <!-- Team Leave Approvals -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-25 border-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people"></i> Team Leave Approvals</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Employee</th>
                                <th>Type & Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($team_leaves)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No team leave requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach($team_leaves as $l): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= h($l['username']) ?></td>
                                    <td>
                                        <div class="small fw-bold text-muted"><?= h($l['type'] ?? 'Casual') ?></div>
                                        <div class="small"><?= date('M j', strtotime($l['start_date'])) ?> - <?= date('M j, Y', strtotime($l['end_date'])) ?></div>
                                    </td>
                                    <td class="text-truncate" style="max-width: 150px;" title="<?= h($l['reason']) ?>"><?= h($l['reason']) ?></td>
                                    <td>
                                        <?php
                                            $badge = 'bg-secondary';
                                            if (strpos($l['status'], 'Approved') !== false) $badge = 'bg-success';
                                            if ($l['status'] == 'Rejected') $badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= h($l['status']) ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($l['status'] == 'Pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="approve_leave">
                                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                            <button type="submit" name="status" value="<?= $isSuper ? 'Admin Approved' : 'Manager Approved' ?>" class="btn btn-sm btn-outline-success border-0" title="Approve"><i class="bi bi-check-lg"></i></button>
                                            <button type="submit" name="status" value="Rejected" class="btn btn-sm btn-outline-danger border-0" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        </form>
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
        
        <!-- Team Attendance -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary bg-opacity-10 border-0">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-clock"></i> Today's Team Attendance</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Employee</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($team_attendance)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No one has punched in today.</td></tr>
                            <?php else: ?>
                                <?php foreach($team_attendance as $a): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= h($a['username']) ?></td>
                                    <td><span class="badge bg-soft-success text-success"><i class="bi bi-arrow-down-right"></i> <?= date('g:i A', strtotime($a['clock_in'])) ?></span></td>
                                    <td>
                                        <?php if($a['clock_out']): ?>
                                            <span class="badge bg-soft-danger text-danger"><i class="bi bi-arrow-up-right"></i> <?= date('g:i A', strtotime($a['clock_out'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Working...</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= $a['total_hours'] ? h($a['total_hours']).' hrs' : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Holidays -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
            <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Company Holidays</h6>
                <?php if($isSuper): ?>
                    <button class="btn btn-sm btn-outline-primary p-1 py-0" data-bs-toggle="modal" data-bs-target="#holidayModal"><i class="bi bi-plus"></i></button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if(empty($holidays)): ?>
                        <li class="list-group-item py-4 text-center text-muted small border-0">No holidays added.</li>
                    <?php else: ?>
                        <?php foreach($holidays as $h): ?>
                            <?php $isPast = strtotime($h['date']) < strtotime('today'); ?>
                            <li class="list-group-item border-0 d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-1 <?= $isPast ? 'text-decoration-line-through text-muted' : 'fw-bold' ?>"><?= h($h['title']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-calendar me-1"></i> <?= date('F j, Y', strtotime($h['date'])) ?></small>
                                </div>
                                <?php if($isSuper): ?>
                                <form method="POST" onsubmit="return confirm('Delete this holiday?');">
                                    <input type="hidden" name="action" value="delete_holiday">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Leave Request Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="request_leave">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold text-muted">START DATE</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold text-muted">END DATE</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TYPE</label>
                    <select name="type" class="form-select" required>
                        <option value="Casual">Casual Leave</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Paid">Paid Time Off</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">REASON</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isSuper): ?>
<!-- Add Holiday Modal -->
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
            <div class="modal-header pb-0 border-0">
                <h5 class="modal-title fw-bold">Add Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_holiday">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">HOLIDAY TITLE</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Christmas Day" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DATE</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100">Add Holiday</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
