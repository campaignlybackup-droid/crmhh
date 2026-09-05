<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

$client_id = $_GET['client_id'] ?? null;
$deliverable_id = $_GET['deliverable_id'] ?? null;

// Handle Add/Edit Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' && ($isFounder || isManagerRole($pdo, $user_id))) {
        $task_name = $_POST['task_name'];
        $desc = $_POST['description'];
        $assigned_to = $_POST['assigned_to'];
        $priority = $_POST['priority'] ?? 'Medium';
        $due_date = $_POST['due_date'];
        $c_id = $_POST['client_id'] ?: null;
        $d_id = $_POST['deliverable_id'] ?: null;
        
        $pdo->prepare("INSERT INTO tasks (client_id, deliverable_id, task_name, description, assigned_to, assigned_by, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$c_id, $d_id, $task_name, $desc, $assigned_to, $user_id, $priority, $due_date]);
        
        $new_task_id = $pdo->lastInsertId();
        logActivity($pdo, "Created Task: $task_name", 'Task', $new_task_id);
        
        // Notify user
        if ($assigned_to && $assigned_to != $user_id) {
            $msg = "You have been assigned a new task: $task_name";
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")->execute([$assigned_to, $msg, "tasks.php"]);
        }
        
        $_SESSION['flash_success'] = "Task created and assigned.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if ($action === 'inline_edit') {
        $task_id = $_POST['task_id'];
        
        if (!canAccessEntity($pdo, $user_id, 'Task', $task_id)) {
            $_SESSION['flash_error'] = "403 Forbidden.";
            header("Location: tasks.php");
            exit;
        }
        
        if (isset($_POST['status'])) {
            $status = $_POST['status'];
            $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$status, $task_id]);
            logActivity($pdo, "Updated status to $status", 'Task', $task_id);
            
            // Deliverable completion auto-updater
            if ($status === 'Completed') {
                $pdo->prepare("UPDATE tasks SET completed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$task_id]);
                $stmt = $pdo->prepare("SELECT deliverable_id FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);
                $d_id = $stmt->fetchColumn();
                if ($d_id) {
                    $pdo->prepare("UPDATE deliverables SET completed_quantity = completed_quantity + 1 WHERE id = ?")->execute([$d_id]);
                }
            }
        }
        
        if (isset($_POST['assigned_to']) && ($isFounder || isManagerRole($pdo, $user_id))) {
            $new_assignee = $_POST['assigned_to'];
            $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?")->execute([$new_assignee, $task_id]);
            logActivity($pdo, "Reassigned task", 'Task', $task_id);
            
            if ($new_assignee && $new_assignee != $user_id) {
                $msg = "You have been reassigned a task.";
                $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")->execute([$new_assignee, $msg, "tasks.php"]);
            }
        }
        
        $_SESSION['flash_success'] = "Task updated.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

// Fetch Tasks
$tasksSql = "
    SELECT t.*, c.company_name, d.description as deliverable_desc, 
           u.username as assigned_user,
           (t.due_date < CURRENT_DATE AND t.status != 'Completed' AND t.status != 'Cancelled') as is_overdue
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN deliverables d ON t.deliverable_id = d.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.deleted_at IS NULL
";
$params = [];

if ($client_id) {
    $tasksSql .= " AND t.client_id = ?";
    $params[] = $client_id;
}
if ($deliverable_id) {
    $tasksSql .= " AND t.deliverable_id = ?";
    $params[] = $deliverable_id;
}

if (!$isFounder) {
    $tasksSql .= " AND t.assigned_to IN ($visibleIdsStr)";
}

$tasksSql .= " ORDER BY is_overdue DESC, t.due_date ASC";
$stmt = $pdo->prepare($tasksSql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Fetch users for assignment
$assignableUsers = [];
if ($isFounder || isManagerRole($pdo, $user_id)) {
    if ($isFounder) {
        $assignableUsers = $pdo->query("SELECT id, username FROM users WHERE status='active' AND deleted_at IS NULL ORDER BY username ASC")->fetchAll();
    } else {
        $assignableUsers = $pdo->query("SELECT id, username FROM users WHERE status='active' AND deleted_at IS NULL AND id IN ($visibleIdsStr) ORDER BY username ASC")->fetchAll();
    }
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tasks - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .row-overdue { background-color: #fff3f3 !important; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <?php if ($client_id): ?>
                    <a href="client_view.php?id=<?= h($client_id) ?>" class="text-decoration-none text-muted mb-2 d-block"><i class="bi bi-arrow-left"></i> Back to Command Center</a>
                <?php endif; ?>
                <h2 class="fw-bold mb-0">Task Execution Engine</h2>
            </div>
            <?php if ($isFounder || isManagerRole($pdo, $user_id)): ?>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="bi bi-plus-lg"></i> Assign New Task
            </button>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Task Name</th>
                                <th>Client / Deliverable</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tasks)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No pending tasks.</td></tr>
                            <?php endif; ?>
                            <?php foreach($tasks as $task): ?>
                            <tr class="<?= $task['is_overdue'] ? 'row-overdue' : '' ?>">
                                <td class="ps-4">
                                    <div class="fw-bold"><?= h($task['task_name']) ?></div>
                                    <div class="text-muted small"><?= h($task['description']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-secondary"><?= h($task['company_name'] ?? 'Internal') ?></div>
                                    <div class="text-muted small"><?= h($task['deliverable_desc']) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $pBadge = 'bg-secondary';
                                    if ($task['priority'] == 'High') $pBadge = 'bg-warning text-dark';
                                    if ($task['priority'] == 'Urgent') $pBadge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $pBadge ?>"><?= h($task['priority']) ?></span>
                                </td>
                                <td>
                                    <?php if ($task['is_overdue']): ?>
                                        <span class="badge bg-danger mb-1 d-inline-block"><i class="bi bi-exclamation-circle-fill"></i> OVERDUE</span><br>
                                    <?php endif; ?>
                                    <span class="fw-bold <?= $task['is_overdue'] ? 'text-danger' : '' ?>"><?= date('M j, Y', strtotime($task['due_date'])) ?></span>
                                </td>
                                <td>
                                    <?php if ($isFounder || isManagerRole($pdo, $user_id)): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="inline_edit">
                                        <input type="hidden" name="task_id" value="<?= h($task['id']) ?>">
                                        <select name="assigned_to" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($assignableUsers as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= $task['assigned_to'] == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-person"></i> <?= h($task['assigned_user']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="inline_edit">
                                        <input type="hidden" name="task_id" value="<?= h($task['id']) ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto fw-bold" onchange="this.form.submit()">
                                            <option value="Not Started" <?= $task['status'] == 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                                            <option value="In Progress" <?= $task['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Pending Review" <?= $task['status'] == 'Pending Review' ? 'selected' : '' ?>>Pending Review</option>
                                            <option value="Completed" <?= $task['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isFounder || isManagerRole($pdo, $user_id)): ?>
<div class="modal fade" id="addTaskModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Assign New Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="client_id" value="<?= h($client_id) ?>">
        <input type="hidden" name="deliverable_id" value="<?= h($deliverable_id) ?>">
        
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Task Name</label>
            <input type="text" name="task_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">Priority</label>
                <select name="priority" class="form-select">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">Due Date</label>
                <input type="date" name="due_date" class="form-control" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Assign To</label>
            <select name="assigned_to" class="form-select" required>
                <option value="">Select Employee...</option>
                <?php foreach ($assignableUsers as $u): ?>
                <option value="<?= $u['id'] ?>"><?= h($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary fw-bold">Assign Task</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
