<?php
require_once 'functions.php';
requireSuperAdmin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$filter_user = $_GET['user_id'] ?? '';

$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

$query = "SELECT d.*, u.username FROM daily_work d LEFT JOIN users u ON d.user_id = u.id WHERE d.work_date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($filter_user) {
    $query .= " AND d.user_id = ?";
    $params[] = $filter_user;
}
$query .= " ORDER BY d.work_date DESC, d.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$work_logs = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Team Daily Work</h3>
        <p class="text-muted mb-0">View work logs from all team members.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">START DATE</label>
                <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">END DATE</label>
                <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">USER</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>User</th>
                        <th>Work Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($work_logs)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No work logs found for the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach($work_logs as $log): ?>
                        <tr>
                            <td class="ps-3 fw-bold" style="width: 150px;"><?= h(date('M d, Y', strtotime($log['work_date']))) ?></td>
                            <td style="width: 200px;">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-secondary text-white d-flex justify-content-center align-items-center me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                        <?= strtoupper(substr($log['username'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <?= h($log['username'] ?? 'Unknown') ?>
                                </div>
                            </td>
                            <td><?= nl2br(h($log['description'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
