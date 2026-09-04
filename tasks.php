<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Inline Status Update (AJAX or direct POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status'];
    
    // Verify permission (is it their task, or are they manager/founder)
    $canUpdate = false;
    if ($isFounder) $canUpdate = true;
    else {
        $chk = $pdo->prepare("SELECT user_id FROM task_assignments WHERE task_id = ?");
        $chk->execute([$task_id]);
        $assignees = $chk->fetchAll(PDO::FETCH_COLUMN);
        if (in_array($user_id, $assignees) || (!empty(array_intersect($assignees, $visibleIds)))) {
            $canUpdate = true;
        }
    }
    
    if ($canUpdate) {
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$new_status, $task_id]);
        logActivity($pdo, 'Updated Task Status to ' . $new_status, 'Task', $task_id);
    }
    
    header("Location: tasks.php");
    exit;
}

// Fetch Tasks
$tasksSql = "SELECT t.*, p.project_name, c.client_name 
             FROM tasks t 
             LEFT JOIN projects p ON t.project_id = p.id
             LEFT JOIN clients c ON p.client_id = c.id";

if (!$isFounder) {
    $tasksSql .= " WHERE t.id IN (SELECT task_id FROM task_assignments WHERE user_id IN ($visibleIdsStr))";
}
$tasksSql .= " ORDER BY t.due_date ASC";
$stmt = $pdo->query($tasksSql);
$tasks = $stmt ? $stmt->fetchAll() : [];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Tasks</h3>
    <?php if ($isManager): ?>
    <button type="button" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i> New Task
    </button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Task</th>
                        <th>Client / Project</th>
                        <th>Deadline</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No tasks found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): 
                            $isOverdue = (strtotime($task['due_date']) < strtotime('today') && $task['status'] != 'Done');
                        ?>
                            <tr class="<?= $isOverdue ? 'bg-soft-danger' : '' ?>">
                                <td class="ps-4 fw-bold">
                                    <a href="task_view.php?id=<?= $task['id'] ?>" class="text-body text-decoration-none hover-primary">
                                        <?= h($task['task_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="small fw-bold"><?= h($task['client_name'] ?? 'No Client') ?></div>
                                    <div class="small text-muted"><?= h($task['project_name'] ?? 'No Project') ?></div>
                                </td>
                                <td>
                                    <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                                        <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                    </span>
                                    <?php if ($isOverdue): ?><i class="bi bi-exclamation-circle text-danger ms-1"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $pClass = ['High'=>'danger','Medium'=>'warning','Low'=>'success'][$task['priority']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-soft-<?= $pClass ?> text-<?= $pClass ?>"><?= h($task['priority']) ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="tasks.php" class="m-0">
                                        <input type="hidden" name="update_task_status" value="1">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                                            <option value="To Do" <?= $task['status']=='To Do'?'selected':'' ?>>To Do</option>
                                            <option value="In Progress" <?= $task['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                                            <option value="Review" <?= $task['status']=='Review'?'selected':'' ?>>Review</option>
                                            <option value="Done" <?= $task['status']=='Done'?'selected':'' ?>>Done</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="task_view.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-light border">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
