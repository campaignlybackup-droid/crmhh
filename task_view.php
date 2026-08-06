<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();
$task_id = $_GET['id'] ?? null;

if (!$task_id) {
    header("Location: tasks.php");
    exit;
}

// Fetch Task
$stmt = $pdo->prepare("SELECT t.*, p.project_name, u.username as assigned_user FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task || (!$isSuper && $task['assigned_to'] != $user_id)) {
    $_SESSION['flash_error'] = "Task not found or unauthorized.";
    header("Location: tasks.php");
    exit;
}

// Handle Forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_task') {
        $status = $_POST['status'];
        
        if ($isSuper) {
            $task_name = $_POST['task_name'];
            $assigned_to = $_POST['assigned_to'] ?: null;
            $due_date = $_POST['due_date'] ?: null;
            $priority = $_POST['priority'];
            $project_id = $_POST['project_id'] ?: null;

            $upd = $pdo->prepare("UPDATE tasks SET task_name=?, status=?, assigned_to=?, due_date=?, priority=?, project_id=? WHERE id=?");
            $upd->execute([$task_name, $status, $assigned_to, $due_date, $priority, $project_id, $task_id]);
            logActivity($pdo, 'Updated Task', 'Task', $task_id, "Status: $status");
            
            if ($assigned_to && $assigned_to != $task['assigned_to']) {
                addNotification($pdo, $assigned_to, "You have been assigned to task: $task_name");
            }
        } else {
            $upd = $pdo->prepare("UPDATE tasks SET status=? WHERE id=?");
            $upd->execute([$status, $task_id]);
            logActivity($pdo, 'Updated Task Status', 'Task', $task_id, "Status: $status");
        }
        
        // Background Sync: Generate/Update Calendar Event for the Due Date
        $final_due_date = $isSuper ? ($_POST['due_date'] ?: null) : $task['due_date'];
        $final_assigned = $isSuper ? ($_POST['assigned_to'] ?: null) : $task['assigned_to'];
        $final_name = $isSuper ? $_POST['task_name'] : $task['task_name'];
        
        if ($final_due_date && $final_assigned) {
            $checkEvt = $pdo->prepare("SELECT id FROM calendar_events WHERE reference_id = ? AND type = 'Task Deadline'");
            $checkEvt->execute([$task_id]);
            $existing_evt = $checkEvt->fetchColumn();
            
            $event_title = "Task Due: $final_name";

            if ($existing_evt) {
                $updEvt = $pdo->prepare("UPDATE calendar_events SET title=?, start_time=?, end_time=?, user_id=? WHERE id=?");
                $updEvt->execute([$event_title, $final_due_date . ' 23:59:59', $final_due_date . ' 23:59:59', $final_assigned, $existing_evt]);
            } else {
                $insEvt = $pdo->prepare("INSERT INTO calendar_events (user_id, title, start_time, end_time, type, reference_id) VALUES (?, ?, ?, ?, 'Task Deadline', ?)");
                $insEvt->execute([$final_assigned, $event_title, $final_due_date . ' 23:59:59', $final_due_date . ' 23:59:59', $task_id]);
            }
        }

        $_SESSION['flash_success'] = "Task updated.";
        header("Location: task_view.php?id=$task_id");
        exit;
    }
    elseif ($action === 'add_document') {
        $title = $_POST['title'];
        $type = $_POST['type'];
        $url = $_POST['url'];
        
        $ins = $pdo->prepare("INSERT INTO documents (entity_type, entity_id, title, type, url) VALUES ('Task', ?, ?, ?, ?)");
        $ins->execute([$task_id, $title, $type, $url]);
        
        $_SESSION['flash_success'] = "Document added.";
        header("Location: task_view.php?id=$task_id");
        exit;
    }
    elseif ($action === 'add_checklist') {
        $title = $_POST['title'];
        $ins = $pdo->prepare("INSERT INTO checklists (entity_type, entity_id, title) VALUES ('Task', ?, ?)");
        $ins->execute([$task_id, $title]);
        
        header("Location: task_view.php?id=$task_id");
        exit;
    }
    elseif ($action === 'toggle_checklist') {
        $checklist_id = $_POST['checklist_id'];
        $val = $_POST['is_completed'] ? 1 : 0;
        $upd = $pdo->prepare("UPDATE checklists SET is_completed=? WHERE id=?");
        $upd->execute([$val, $checklist_id]);
        exit; // AJAX response
    }
    elseif ($action === 'add_comment') {
        $comment = $_POST['comment'];
        $ins = $pdo->prepare("INSERT INTO comments (entity_type, entity_id, user_id, comment) VALUES ('Task', ?, ?, ?)");
        $ins->execute([$task_id, $user_id, $comment]);
        
        header("Location: task_view.php?id=$task_id");
        exit;
    }
}

// Fetch Components
$checklists = $pdo->prepare("SELECT * FROM checklists WHERE entity_type='Task' AND entity_id=? ORDER BY id ASC");
$checklists->execute([$task_id]);
$checklists = $checklists->fetchAll();

$documents = $pdo->prepare("SELECT * FROM documents WHERE entity_type='Task' AND entity_id=? ORDER BY id ASC");
$documents->execute([$task_id]);
$documents = $documents->fetchAll();

$comments = $pdo->prepare("SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE entity_type='Task' AND entity_id=? ORDER BY c.created_at ASC");
$comments->execute([$task_id]);
$comments = $comments->fetchAll();

$projects = $pdo->query("SELECT id, project_name FROM projects WHERE deleted_at IS NULL ORDER BY project_name")->fetchAll();
$usersList = $pdo->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username")->fetchAll();

$all_statuses = ['To Do', 'In Progress', 'Review', 'Done'];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="tasks.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Tasks</a>
        <h2 class="fw-bold mb-0 mt-1"><?= h($task['task_name']) ?></h2>
        <div class="text-muted small mt-1">
            <i class="bi bi-folder me-1"></i> <?= h($task['project_name'] ?? 'No Project') ?> &nbsp;|&nbsp; 
            <i class="bi bi-person-badge me-1"></i> Assignee: <?= h($task['assigned_user'] ?? 'Unassigned') ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Task Details -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Task Details</h6>
                <?php
                    $sc = 'bg-secondary';
                    if ($task['status'] == 'In Progress') $sc = 'bg-primary';
                    if ($task['status'] == 'Review') $sc = 'bg-warning text-dark';
                    if ($task['status'] == 'Done') $sc = 'bg-success';
                ?>
                <span class="badge <?= $sc ?> fs-6"><?= h($task['status']) ?></span>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="update_task">
                    
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">TASK NAME</label>
                        <input type="text" name="task_name" class="form-control" value="<?= h($task['task_name']) ?>" required <?= !$isSuper ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">STATUS</label>
                        <select name="status" class="form-select">
                            <?php foreach($all_statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">PRIORITY</label>
                        <select name="priority" class="form-select" <?= !$isSuper ? 'disabled' : '' ?>>
                            <?php foreach(['Low', 'Medium', 'High'] as $p): ?>
                                <option value="<?= $p ?>" <?= $task['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!$isSuper): ?><input type="hidden" name="priority" value="<?= $task['priority'] ?>"><?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">PROJECT</label>
                        <select name="project_id" class="form-select" <?= !$isSuper ? 'disabled' : '' ?>>
                            <option value="">No Project</option>
                            <?php foreach($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $task['project_id'] == $p['id'] ? 'selected' : '' ?>><?= h($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!$isSuper): ?><input type="hidden" name="project_id" value="<?= $task['project_id'] ?>"><?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">DUE DATE</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $task['due_date'] ?>" <?= !$isSuper ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">ASSIGN TO</label>
                        <select name="assigned_to" class="form-select" <?= !$isSuper ? 'disabled' : '' ?>>
                            <option value="">Unassigned</option>
                            <?php foreach($usersList as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $task['assigned_to'] == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!$isSuper): ?><input type="hidden" name="assigned_to" value="<?= $task['assigned_to'] ?>"><?php endif; ?>
                    </div>

                    <div class="col-12 mt-3 text-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Update Task</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <!-- Checklists -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light border-0">
                        <h6 class="fw-bold mb-0">Checklist</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($checklists as $chk): ?>
                                <li class="list-group-item px-0 py-2 border-0 d-flex align-items-center">
                                    <input class="form-check-input me-2 chk-toggle" type="checkbox" data-id="<?= $chk['id'] ?>" <?= $chk['is_completed'] ? 'checked' : '' ?>>
                                    <span class="<?= $chk['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= h($chk['title']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="action" value="add_checklist">
                            <input type="text" name="title" class="form-control form-control-sm" placeholder="New checklist item..." required>
                            <button class="btn btn-sm btn-secondary"><i class="bi bi-plus"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light border-0">
                        <h6 class="fw-bold mb-0">Documents & Links</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($documents as $doc): ?>
                                <li class="list-group-item px-0 py-2 border-0 d-flex align-items-center">
                                    <span class="badge bg-secondary bg-opacity-25 text-body me-2" style="font-size:0.6rem;"><?= h($doc['type']) ?></span>
                                    <a href="<?= h($doc['url']) ?>" target="_blank" class="text-decoration-none text-truncate d-inline-block" style="max-width: 150px;"><?= h($doc['title']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#addDocModal">
                            <i class="bi bi-link-45deg"></i> Add Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comments Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 d-flex flex-column">
            <div class="card-header bg-light border-0 pb-3">
                <h6 class="fw-bold mb-0">Task Discussion</h6>
            </div>
            <div class="card-body d-flex flex-column flex-grow-1" style="max-height: 600px;">
                <div class="flex-grow-1 overflow-auto mb-3 pe-2">
                    <?php if (empty($comments)): ?>
                        <div class="text-muted small text-center py-4">No comments yet.</div>
                    <?php else: ?>
                        <?php foreach($comments as $c): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong class="small"><?= h($c['username']) ?></strong>
                                    <span class="text-muted" style="font-size: 0.65rem;"><?= date('M d, g:i A', strtotime($c['created_at'])) ?></span>
                                </div>
                                <div class="bg-light p-2 rounded small text-body">
                                    <?= nl2br(h($c['comment'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" class="mt-auto">
                    <input type="hidden" name="action" value="add_comment">
                    <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Write a comment..." required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Post Comment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Doc Modal -->
<div class="modal fade" id="addDocModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Link / Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_document">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TITLE</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TYPE</label>
                    <select name="type" class="form-select" required>
                        <option value="Google Drive">Google Drive</option>
                        <option value="Figma">Figma</option>
                        <option value="PDF Link">PDF Link</option>
                        <option value="Loom">Loom Video</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">URL</label>
                    <input type="url" name="url" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Link</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.chk-toggle').forEach(chk => {
    chk.addEventListener('change', function() {
        const id = this.dataset.id;
        const checked = this.checked ? 1 : 0;
        const span = this.nextElementSibling;
        
        if (checked) {
            span.classList.add('text-decoration-line-through', 'text-muted');
        } else {
            span.classList.remove('text-decoration-line-through', 'text-muted');
        }
        
        fetch('task_view.php?id=<?= $task_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=toggle_checklist&checklist_id=${id}&is_completed=${checked}`
        });
    });
});
</script>

<?php include 'footer.php'; ?>
