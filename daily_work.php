<?php
require_once 'functions.php';
requireLogin();

$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $work_date = $_POST['work_date'];
            $description = $_POST['description'];

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO daily_work (user_id, work_date, description) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $work_date, $description]);
                $_SESSION['flash_success'] = "Daily work logged successfully.";
            } else if ($action === 'edit' && $id) {
                // Ensure the log belongs to this user
                $stmt = $pdo->prepare("UPDATE daily_work SET work_date=?, description=? WHERE id=? AND user_id=?");
                $stmt->execute([$work_date, $description, $id, $user_id]);
                $_SESSION['flash_success'] = "Daily work updated successfully.";
            }
            header("Location: daily_work.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM daily_work WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $_SESSION['flash_success'] = "Work log deleted.";
            header("Location: daily_work.php");
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM daily_work WHERE user_id = ? ORDER BY work_date DESC, created_at DESC");
$stmt->execute([$user_id]);
$work_logs = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">My Daily Work</h3>
        <p class="text-muted mb-0">Log your daily presence and tasks completed.</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#workModal" onclick="resetWorkForm()">
            <i class="bi bi-plus-lg"></i> Log Work
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Work Description</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($work_logs)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No work logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($work_logs as $log): ?>
                        <tr>
                            <td class="ps-3 fw-bold" style="width: 150px;"><?= h(date('M d, Y', strtotime($log['work_date']))) ?></td>
                            <td><?= nl2br(h($log['description'])) ?></td>
                            <td class="text-end pe-3" style="width: 120px;">
                                <button class="btn btn-sm btn-outline-primary" onclick='editWork(<?= json_encode($log) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this work log?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $log['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Work Modal -->
<div class="modal fade" id="workModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="workModalTitle">Log Daily Work</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="workAction" value="add">
                <input type="hidden" name="id" id="workId" value="">
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">WORK DATE *</label>
                    <input type="date" name="work_date" id="workDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">DESCRIPTION *</label>
                    <textarea name="description" id="workDescription" class="form-control" rows="4" required placeholder="What did you work on today?"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Work Log</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetWorkForm() {
    document.getElementById('workAction').value = 'add';
    document.getElementById('workId').value = '';
    document.getElementById('workModalTitle').innerText = 'Log Daily Work';
    document.getElementById('workDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('workDescription').value = '';
}

function editWork(log) {
    document.getElementById('workAction').value = 'edit';
    document.getElementById('workId').value = log.id;
    document.getElementById('workModalTitle').innerText = 'Edit Daily Work';
    document.getElementById('workDate').value = log.work_date;
    document.getElementById('workDescription').value = log.description;
    
    var modal = new bootstrap.Modal(document.getElementById('workModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
