<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

$projects = $pdo->query("SELECT id, project_name FROM projects ORDER BY project_name ASC")->fetchAll();
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'bulk') {
            $bulk_action = $_POST['bulk_action'] ?? '';
            $selected_ids = $_POST['selected_ids'] ?? [];
            if (!empty($selected_ids)) {
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $params = $selected_ids;

                if (!$isSuper) {
                    $checkStmt = $pdo->prepare("SELECT id FROM tasks WHERE id IN ($placeholders) AND assigned_to = ?");
                    $checkParams = $selected_ids;
                    $checkParams[] = $user_id;
                    $checkStmt->execute($checkParams);
                    $selected_ids = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $params = $selected_ids;
                }

                if (!empty($selected_ids)) {
                    if ($bulk_action === 'delete' && $isSuper) {
                        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id IN ($placeholders)");
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " tasks deleted.";
                    } elseif ($bulk_action === 'status') {
                        $new_status = $_POST['bulk_status'] ?? '';
                        if ($new_status) {
                            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id IN ($placeholders)");
                            array_unshift($params, $new_status);
                            $stmt->execute($params);
                            $_SESSION['flash_success'] = count($selected_ids) . " tasks updated.";
                        }
                    } elseif ($bulk_action === 'assign' && $isSuper) {
                        $new_assignee = $_POST['bulk_assignee'] ?? null;
                        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id IN ($placeholders)");
                        array_unshift($params, $new_assignee ?: null);
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " tasks reassigned.";
                    }
                }
            }
            header("Location: tasks.php");
            exit;
        } else if ($action === 'add' && $isSuper) {
            $task_name = $_POST['task_name'];
            $status = $_POST['status'];
            $assigned_to = $_POST['assigned_to'] ?: null;
            $due_date = $_POST['due_date'] ?: null;
            $priority = $_POST['priority'];
            $project_id = $_POST['project_id'] ?: null;

            $stmt = $pdo->prepare("INSERT INTO tasks (task_name, status, assigned_to, due_date, priority, project_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$task_name, $status, $assigned_to, $due_date, $priority, $project_id]);
            $new_id = $pdo->lastInsertId();
            logActivity($pdo, 'Created Task', 'Task', $new_id, $task_name);
            
            if ($assigned_to) {
                addNotification($pdo, $assigned_to, "You have been assigned a new task: $task_name");
            }
            $_SESSION['flash_success'] = "Task created successfully.";
            header("Location: tasks.php");
            exit;
        } else if ($action === 'edit') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();

            if ($isSuper || ($task && $task['assigned_to'] == $user_id)) {
                if ($isSuper) {
                    $task_name = $_POST['task_name'];
                    $assigned_to = $_POST['assigned_to'] ?: null;
                    $due_date = $_POST['due_date'] ?: null;
                    $priority = $_POST['priority'];
                    $project_id = $_POST['project_id'] ?: null;

                    $stmt = $pdo->prepare("UPDATE tasks SET task_name=?, status=?, assigned_to=?, due_date=?, priority=?, project_id=? WHERE id=?");
                    $stmt->execute([$task_name, $status, $assigned_to, $due_date, $priority, $project_id, $id]);
                    logActivity($pdo, 'Updated Task', 'Task', $id, "Status: $status");
                    
                    if ($assigned_to && $assigned_to != $task['assigned_to']) {
                        addNotification($pdo, $assigned_to, "You have been assigned to task: $task_name");
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE tasks SET status=? WHERE id=?");
                    $stmt->execute([$status, $id]);
                    logActivity($pdo, 'Updated Task Status', 'Task', $id, "Status: $status");
                }
                $_SESSION['flash_success'] = "Task updated successfully.";
            } else {
                $_SESSION['flash_error'] = "Unauthorized to edit this task.";
            }
            header("Location: tasks.php");
            exit;
        } elseif ($action === 'delete' && $isSuper) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, 'Deleted Task', 'Task', $id, "ID: $id");
            $_SESSION['flash_success'] = "Task deleted.";
            header("Location: tasks.php");
            exit;
        }
    }
}

$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_project = $_GET['project_id'] ?? '';
$filter_assignee = $_GET['assigned_to'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$view = $_GET['view'] ?? 'board'; // Default to board for tasks

$query = "SELECT t.*, p.project_name, u.username as assigned_user FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1 ";
$params = [];

if (!$isSuper) {
    $query .= " AND t.assigned_to = ? ";
    $params[] = $user_id;
} else if ($filter_assignee) {
    $query .= " AND t.assigned_to = ? ";
    $params[] = $filter_assignee;
}

if ($search) {
    $query .= " AND t.task_name LIKE ? ";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND t.status = ? ";
    $params[] = $filter_status;
}
if ($filter_project) {
    $query .= " AND t.project_id = ? ";
    $params[] = $filter_project;
}
if ($start_date) {
    $query .= " AND t.due_date >= ? ";
    $params[] = $start_date;
}
if ($end_date) {
    $query .= " AND t.due_date <= ? ";
    $params[] = $end_date;
}
$query .= " ORDER BY t.due_date ASC, t.priority ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$all_statuses = ['To Do', 'In Progress', 'Review', 'Done'];

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-4">
        <h3 class="fw-bold mb-0">Tasks</h3>
    </div>
    <div class="col-md-8 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
        <div class="btn-group me-2" role="group">
            <a href="?view=table&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&assigned_to=<?= urlencode($filter_assignee) ?>" class="btn <?= $view === 'table' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-list-task"></i> Table</a>
            <a href="?view=board&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&assigned_to=<?= urlencode($filter_assignee) ?>" class="btn <?= $view === 'board' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-kanban"></i> Board</a>
        </div>
        <?php if ($isSuper): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Task
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2 flex-wrap">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= h($search) ?>" style="min-width: 150px; flex: 1;">
            <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>" title="Start Due Date" style="max-width: 150px;">
            <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>" title="End Due Date" style="max-width: 150px;">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach($all_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="project_id" class="form-select" style="max-width: 150px;">
                <option value="">All Projects</option>
                <?php foreach($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($filter_project == $p['id']) ? 'selected' : '' ?>><?= h($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSuper): ?>
            <select name="assigned_to" class="form-select" style="max-width: 150px;">
                <option value="">All Assignees</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($filter_assignee == $u['id']) ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="tasks.php?view=<?= h($view) ?>" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<?php if ($view === 'board'): ?>
    <style>
        .kanban-board { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 20px; min-height: 60vh; align-items: flex-start; }
        .kanban-column { background-color: var(--light-bg); border-radius: 10px; min-width: 300px; max-width: 300px; padding: 15px; flex-shrink: 0; border: 1px solid var(--border-color); }
        .kanban-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-color); border-left: 4px solid var(--primary-color); }
        .kanban-card.priority-High { border-left-color: #dc3545; }
        .kanban-card.priority-Medium { border-left-color: #ffc107; }
        .kanban-card.priority-Low { border-left-color: #6c757d; }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .kanban-card-title { font-weight: 600; font-size: 1rem; margin-bottom: 5px; color: var(--text-main); line-height: 1.3; }
    </style>
    
    <div class="kanban-board">
        <?php foreach ($all_statuses as $status_col): ?>
            <?php 
                $col_tasks = array_filter($tasks, function($t) use ($status_col) { return $t['status'] === $status_col; });
            ?>
            <div class="kanban-column">
                <h6 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                    <?= $status_col ?>
                    <span class="badge bg-secondary rounded-pill"><?= count($col_tasks) ?></span>
                </h6>
                
                <?php foreach ($col_tasks as $task): ?>
                    <?php 
                        $isOverdue = ($task['status'] != 'Done' && strtotime($task['due_date']) < strtotime('today'));
                        $dateClass = $isOverdue ? 'text-danger fw-bold' : 'text-muted';
                    ?>
                    <div class="kanban-card priority-<?= $task['priority'] ?>" onclick='editTask(<?= json_encode($task) ?>)'>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-light text-dark border small"><?= h($task['project_name'] ?: 'No Project') ?></span>
                        </div>
                        <div class="kanban-card-title"><?= h($task['task_name']) ?></div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <div class="d-flex align-items-center" title="<?= h($task['assigned_user'] ?? 'Unassigned') ?>">
                                <div class="rounded-circle bg-secondary text-white d-flex justify-content-center align-items-center" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($task['assigned_user'] ?? '?', 0, 1)) ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="small <?= $dateClass ?>">
                                    <i class="bi bi-calendar"></i> <?= h($task['due_date'] ?? 'No Date') ?>
                                </div>
                                <?php if ($isSuper): ?>
                                <form method="POST" class="m-0 d-inline" onsubmit="return confirm('Delete this task?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1 border-0" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- TABLE VIEW -->
    <form id="bulkForm" method="POST" action="tasks.php">
        <input type="hidden" name="action" value="bulk">
        
        <div id="bulkActionBar" class="card mb-3 border-primary shadow-sm" style="display: none;">
            <div class="card-body bg-primary bg-opacity-10 py-2 d-flex align-items-center gap-3 flex-wrap">
                <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
                <select name="bulk_action" id="bulkActionSelect" class="form-select form-select-sm" style="width: auto;" onchange="toggleBulkOptions()">
                    <option value="">Choose bulk action...</option>
                    <option value="status">Change Status</option>
                    <?php if ($isSuper): ?><option value="assign">Reassign</option><?php endif; ?>
                    <?php if ($isSuper): ?><option value="delete">Delete</option><?php endif; ?>
                </select>
                
                <select name="bulk_status" id="bulkStatusSelect" class="form-select form-select-sm" style="width: auto; display: none;">
                    <?php foreach($all_statuses as $s) echo "<option value=\"$s\">$s</option>"; ?>
                </select>
                
                <?php if ($isSuper): ?>
                <select name="bulk_assignee" id="bulkAssigneeSelect" class="form-select form-select-sm" style="width: auto; display: none;">
                    <option value="">Unassigned</option>
                    <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                </select>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Apply this bulk action?')">Apply</button>
            </div>
        </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40px;" class="ps-3"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th>Task Name</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($tasks)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No tasks found.</td></tr>
                        <?php else: ?>
                            <?php foreach($tasks as $task): ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $task['id'] ?>"></td>
                                <td class="fw-bold"><?= h($task['task_name']) ?></td>
                                <td><?= h($task['project_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                        $sc = 'bg-soft-secondary';
                                        if ($task['status'] == 'In Progress') $sc = 'bg-soft-primary';
                                        if ($task['status'] == 'Review') $sc = 'bg-soft-warning';
                                        if ($task['status'] == 'Done') $sc = 'bg-soft-success';
                                    ?>
                                    <span class="badge badge-status <?= $sc ?>"><?= h($task['status']) ?></span>
                                </td>
                                <td>
                                    <?php
                                        $pc = 'bg-secondary';
                                        if ($task['priority'] == 'High') $pc = 'bg-danger';
                                        if ($task['priority'] == 'Medium') $pc = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?= $pc ?>"><?= h($task['priority']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2" style="width: 25px; height: 25px; font-size: 0.75rem;">
                                            <?= strtoupper(substr($task['assigned_user'] ?? '?', 0, 1)) ?>
                                        </div>
                                        <?= h($task['assigned_user'] ?? 'Unassigned') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $isOverdue = ($task['status'] != 'Done' && strtotime($task['due_date']) < strtotime('today'));
                                        $dateClass = $isOverdue ? 'text-danger fw-bold' : '';
                                    ?>
                                    <span class="<?= $dateClass ?>"><?= h($task['due_date'] ?? '-') ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editTask(<?= json_encode($task) ?>)'><i class="bi bi-pencil"></i></button>
                                    <?php if ($isSuper): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this task?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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
    </form>
<?php endif; ?>

<!-- Add/Edit Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="taskModalTitle">Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="taskAction" value="add">
                <input type="hidden" name="id" id="taskId" value="">
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">TASK NAME *</label>
                    <input type="text" name="task_name" id="taskName" class="form-control" required <?= !$isSuper ? 'readonly' : '' ?>>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">STATUS</label>
                    <select name="status" id="taskStatus" class="form-select">
                        <?php foreach($all_statuses as $s) echo "<option value=\"$s\">$s</option>"; ?>
                    </select>
                </div>

                <?php if ($isSuper): ?>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PROJECT</label>
                    <select name="project_id" id="taskProject" class="form-select">
                        <option value="">No Project...</option>
                        <?php foreach($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label text-muted small fw-bold">PRIORITY</label>
                        <select name="priority" id="taskPriority" class="form-select">
                            <?php foreach(['Low', 'Medium', 'High'] as $p) echo "<option value=\"$p\">$p</option>"; ?>
                        </select>
                    </div>

                    <div class="col-6 mb-3">
                        <label class="form-label text-muted small fw-bold">DUE DATE</label>
                        <input type="date" name="due_date" id="taskDue" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                    <select name="assigned_to" id="taskAssigned" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= h($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Task</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    <?php if ($isSuper): ?>
    document.getElementById('taskAction').value = 'add';
    document.getElementById('taskId').value = '';
    document.getElementById('taskModalTitle').innerText = 'Add Task';
    document.getElementById('taskName').value = '';
    document.getElementById('taskStatus').value = 'To Do';
    document.getElementById('taskProject').value = '';
    document.getElementById('taskPriority').value = 'Medium';
    document.getElementById('taskDue').value = '';
    document.getElementById('taskAssigned').value = '';
    <?php endif; ?>
}

function editTask(task) {
    document.getElementById('taskAction').value = 'edit';
    document.getElementById('taskId').value = task.id;
    document.getElementById('taskModalTitle').innerText = 'Edit Task';
    document.getElementById('taskName').value = task.task_name;
    document.getElementById('taskStatus').value = task.status;
    <?php if ($isSuper): ?>
    document.getElementById('taskProject').value = task.project_id || '';
    document.getElementById('taskPriority').value = task.priority;
    document.getElementById('taskDue').value = task.due_date;
    document.getElementById('taskAssigned').value = task.assigned_to || '';
    <?php endif; ?>
    
    var modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

// Bulk Actions Logic
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');

    function updateBulkBar() {
        if (!bulkActionBar) return;
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        selectedCount.textContent = checkedCount;
        if (checkedCount > 0) {
            bulkActionBar.style.display = 'block';
        } else {
            bulkActionBar.style.display = 'none';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked && selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    });
});

function toggleBulkOptions() {
    const action = document.getElementById('bulkActionSelect').value;
    document.getElementById('bulkStatusSelect').style.display = (action === 'status') ? 'inline-block' : 'none';
    const assignSelect = document.getElementById('bulkAssigneeSelect');
    if (assignSelect) assignSelect.style.display = (action === 'assign') ? 'inline-block' : 'none';
}
</script>

<?php include 'footer.php'; ?>
