<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    header("Location: projects.php");
    exit;
}

// Fetch project
$stmt = $pdo->prepare("SELECT p.*, c.client_name, u.username as assigned_user FROM projects p LEFT JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.assigned_to = u.id WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project || (!$isSuper && $project['assigned_to'] != $user_id)) {
    $_SESSION['flash_error'] = "Project not found or unauthorized.";
    header("Location: projects.php");
    exit;
}

// Handle Form Submissions for the Active Stage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stage_id = $_POST['stage_id'] ?? null;
    
    if ($action === 'update_stage' && $stage_id) {
        $owner_id = $_POST['owner_id'] ?: null;
        $approver_id = $_POST['approver_id'] ?: null;
        $deadline = $_POST['deadline'] ?: null;
        
        $upd = $pdo->prepare("UPDATE project_stages SET owner_id=?, approver_id=?, deadline=? WHERE id=?");
        $upd->execute([$owner_id, $approver_id, $deadline, $stage_id]);
        
        // Background Sync: Generate Calendar Event for the Deadline
        if ($deadline) {
            // Check if event exists
            $checkEvt = $pdo->prepare("SELECT id FROM calendar_events WHERE reference_id = ? AND type = 'Stage Deadline'");
            $checkEvt->execute([$stage_id]);
            $existing_evt = $checkEvt->fetchColumn();
            
            // Get stage name
            $stg = $pdo->prepare("SELECT stage_name FROM project_stages WHERE id = ?");
            $stg->execute([$stage_id]);
            $stg_name = $stg->fetchColumn();
            $event_title = "Deadline: $stg_name (" . $project['project_name'] . ")";

            if ($existing_evt) {
                $updEvt = $pdo->prepare("UPDATE calendar_events SET title=?, start_time=?, end_time=?, user_id=? WHERE id=?");
                $updEvt->execute([$event_title, $deadline . ' 23:59:59', $deadline . ' 23:59:59', $owner_id, $existing_evt]);
            } else {
                $insEvt = $pdo->prepare("INSERT INTO calendar_events (user_id, title, start_time, end_time, type, reference_id) VALUES (?, ?, ?, ?, 'Stage Deadline', ?)");
                $insEvt->execute([$owner_id, $event_title, $deadline . ' 23:59:59', $deadline . ' 23:59:59', $stage_id]);
            }
        }
        
        $_SESSION['flash_success'] = "Stage details updated.";
        header("Location: project_view.php?id=$project_id&stage_id=$stage_id");
        exit;
    }
    elseif ($action === 'add_document' && $stage_id) {
        $title = $_POST['title'];
        $type = $_POST['type'];
        $url = $_POST['url'];
        
        $ins = $pdo->prepare("INSERT INTO documents (entity_type, entity_id, title, type, url) VALUES ('Stage', ?, ?, ?, ?)");
        $ins->execute([$stage_id, $title, $type, $url]);
        
        $_SESSION['flash_success'] = "Document added.";
        header("Location: project_view.php?id=$project_id&stage_id=$stage_id");
        exit;
    }
    elseif ($action === 'add_checklist' && $stage_id) {
        $title = $_POST['title'];
        $ins = $pdo->prepare("INSERT INTO checklists (entity_type, entity_id, title) VALUES ('Stage', ?, ?)");
        $ins->execute([$stage_id, $title]);
        
        header("Location: project_view.php?id=$project_id&stage_id=$stage_id");
        exit;
    }
    elseif ($action === 'toggle_checklist') {
        $checklist_id = $_POST['checklist_id'];
        $val = $_POST['is_completed'] ? 1 : 0;
        $upd = $pdo->prepare("UPDATE checklists SET is_completed=? WHERE id=?");
        $upd->execute([$val, $checklist_id]);
        exit; // AJAX response
    }
    elseif ($action === 'add_comment' && $stage_id) {
        $comment = $_POST['comment'];
        $ins = $pdo->prepare("INSERT INTO comments (entity_type, entity_id, user_id, comment) VALUES ('Stage', ?, ?, ?)");
        $ins->execute([$stage_id, $user_id, $comment]);
        
        header("Location: project_view.php?id=$project_id&stage_id=$stage_id");
        exit;
    }
    elseif ($action === 'approve_stage' && $stage_id) {
        // Mark current as Approved
        $upd = $pdo->prepare("UPDATE project_stages SET status='Approved', completion_date=NOW() WHERE id=?");
        $upd->execute([$stage_id]);
        
        // Find NEXT stage
        $stgs = $pdo->prepare("SELECT id, stage_name FROM project_stages WHERE project_id=? ORDER BY id ASC");
        $stgs->execute([$project_id]);
        $all = $stgs->fetchAll();
        
        $next_stage_name = null;
        $next_stage_id = null;
        $found_current = false;
        
        foreach ($all as $s) {
            if ($found_current) {
                $next_stage_name = $s['stage_name'];
                $next_stage_id = $s['id'];
                break;
            }
            if ($s['id'] == $stage_id) {
                $found_current = true;
            }
        }
        
        if ($next_stage_name) {
            // Update next stage to In Progress
            $updNext = $pdo->prepare("UPDATE project_stages SET status='In Progress' WHERE id=?");
            $updNext->execute([$next_stage_id]);
            
            // Update project overall status
            $updProj = $pdo->prepare("UPDATE projects SET status=? WHERE id=?");
            $updProj->execute([$next_stage_name, $project_id]);
            
            // ==========================================
            // WORKFLOW AUTOMATION ENGINE
            // ==========================================
            if ($project['workflow_template_id']) {
                $qTasks = $pdo->prepare("SELECT * FROM workflow_tasks WHERE template_id = ? AND trigger_stage_name = ?");
                $qTasks->execute([$project['workflow_template_id'], $next_stage_name]);
                $auto_tasks = $qTasks->fetchAll();
                
                if (!empty($auto_tasks)) {
                    $insTask = $pdo->prepare("INSERT INTO tasks (task_name, status, assigned_to, due_date, priority, project_id) VALUES (?, 'To Do', ?, ?, 'Medium', ?)");
                    foreach ($auto_tasks as $at) {
                        // Calculate estimated due date based on estimated hours (assuming 8 hr workday roughly, or just add days)
                        // Simple approach: Add (estimated_hours / 8) days to current date, minimum 1 day.
                        $days_to_add = max(1, ceil($at['estimated_hours'] / 8));
                        $due_date = date('Y-m-d', strtotime("+$days_to_add days"));
                        
                        // If assignee is null in template, assign to project lead
                        $task_assignee = $at['default_assignee_id'] ?: $project['assigned_to'];
                        
                        $insTask->execute([$at['task_name'], $task_assignee, $due_date, $project_id]);
                        
                        // Notify Assignee
                        if ($task_assignee) {
                            addNotification($pdo, $task_assignee, "Automated Task Assigned: " . $at['task_name'] . " (Project: " . $project['project_name'] . ")");
                        }
                    }
                    $_SESSION['flash_success'] = "Stage Approved! " . count($auto_tasks) . " automated tasks generated for $next_stage_name.";
                } else {
                    $_SESSION['flash_success'] = "Stage Approved! Moved to $next_stage_name.";
                }
            } else {
                $_SESSION['flash_success'] = "Stage Approved! Moved to $next_stage_name.";
            }

            header("Location: project_view.php?id=$project_id&stage_id=$next_stage_id");
        } else {
            $_SESSION['flash_success'] = "Project Fully Completed!";
            header("Location: project_view.php?id=$project_id");
        }
        exit;
    }
}

// Fetch Stages
$stagesStmt = $pdo->prepare("SELECT s.*, o.username as owner_name, a.username as approver_name FROM project_stages s LEFT JOIN users o ON s.owner_id = o.id LEFT JOIN users a ON s.approver_id = a.id WHERE s.project_id = ? ORDER BY s.id ASC");
$stagesStmt->execute([$project_id]);
$stages = $stagesStmt->fetchAll();

if (empty($stages)) {
    $_SESSION['flash_error'] = "No stages found. Please run the migration script.";
    header("Location: projects.php");
    exit;
}

$active_stage_id = $_GET['stage_id'] ?? null;

// Find currently active stage for stepper logic
$current_project_stage_id = $stages[0]['id'];
foreach ($stages as $s) {
    if ($s['stage_name'] === $project['status']) {
        $current_project_stage_id = $s['id'];
        if (!$active_stage_id) $active_stage_id = $s['id'];
        break;
    }
}
if (!$active_stage_id) $active_stage_id = $stages[0]['id'];

$active_stage = null;
foreach ($stages as $s) {
    if ($s['id'] == $active_stage_id) {
        $active_stage = $s;
        break;
    }
}

// Fetch Stage Components
$checklists = $pdo->prepare("SELECT * FROM checklists WHERE entity_type='Stage' AND entity_id=? ORDER BY id ASC");
$checklists->execute([$active_stage_id]);
$checklists = $checklists->fetchAll();

$documents = $pdo->prepare("SELECT * FROM documents WHERE entity_type='Stage' AND entity_id=? ORDER BY id ASC");
$documents->execute([$active_stage_id]);
$documents = $documents->fetchAll();

$comments = $pdo->prepare("SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE entity_type='Stage' AND entity_id=? ORDER BY c.created_at ASC");
$comments->execute([$active_stage_id]);
$comments = $comments->fetchAll();

$usersList = $pdo->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username")->fetchAll();

include 'header.php';
?>

<style>
/* Horizontal Stepper */
.stepper-wrapper {
    display: flex;
    overflow-x: auto;
    padding-bottom: 20px;
    margin-bottom: 20px;
}
.stepper-item {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 120px;
}
.stepper-item::before {
    position: absolute;
    content: "";
    border-bottom: 2px solid var(--border-color);
    width: 100%;
    top: 15px;
    left: -50%;
    z-index: 1;
}
.stepper-item:first-child::before {
    content: none;
}
.stepper-step {
    z-index: 2;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--bs-body-bg);
    border: 2px solid var(--border-color);
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    color: var(--bs-secondary);
    text-decoration: none;
    transition: all 0.3s;
}
.stepper-item.completed .stepper-step {
    background-color: var(--bs-success);
    border-color: var(--bs-success);
    color: white;
}
.stepper-item.completed::before {
    border-bottom-color: var(--bs-success);
}
.stepper-item.active .stepper-step {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.2);
}
.stepper-title {
    margin-top: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    color: var(--bs-secondary);
}
.stepper-item.active .stepper-title {
    color: var(--primary-color);
}
.stepper-item.completed .stepper-title {
    color: var(--bs-success);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="projects.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Projects</a>
        <h2 class="fw-bold mb-0 mt-1"><?= h($project['project_name']) ?></h2>
        <div class="text-muted small mt-1">
            <i class="bi bi-building me-1"></i> <?= h($project['client_name'] ?? 'No Client') ?> &nbsp;|&nbsp; 
            <i class="bi bi-person-badge me-1"></i> Lead: <?= h($project['assigned_user'] ?? 'Unassigned') ?>
        </div>
    </div>
    <div>
        <?php if($project['drive_folder_url']): ?>
            <a href="<?= h($project['drive_folder_url']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-folder-fill text-warning me-2"></i> Master Drive</a>
        <?php endif; ?>
    </div>
</div>

<!-- The Stepper -->
<div class="card mb-4">
    <div class="card-body pt-4">
        <div class="stepper-wrapper">
            <?php 
            $passed_current = false;
            foreach ($stages as $index => $stage): 
                $status_class = '';
                if ($stage['status'] === 'Approved') $status_class = 'completed';
                elseif ($stage['id'] == $current_project_stage_id) {
                    $status_class = 'active';
                    $passed_current = true;
                }
            ?>
                <div class="stepper-item <?= $status_class ?>">
                    <a href="?id=<?= $project_id ?>&stage_id=<?= $stage['id'] ?>" class="stepper-step">
                        <?php if($stage['status'] === 'Approved'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <?= $index + 1 ?>
                        <?php endif; ?>
                    </a>
                    <div class="stepper-title"><?= h($stage['stage_name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Active Stage Dashboard -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Stage: <?= h($active_stage['stage_name']) ?></h5>
                <?php
                    $bc = 'bg-secondary';
                    if ($active_stage['status'] == 'Approved') $bc = 'bg-success';
                    if ($active_stage['status'] == 'In Progress') $bc = 'bg-primary';
                ?>
                <span class="badge <?= $bc ?>"><?= h($active_stage['status']) ?></span>
            </div>
            <div class="card-body">
                
                <!-- Stage Details Form -->
                <form method="POST" class="row g-3 mb-4 bg-secondary bg-opacity-10 p-3 rounded">
                    <input type="hidden" name="action" value="update_stage">
                    <input type="hidden" name="stage_id" value="<?= $active_stage['id'] ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">OWNER</label>
                        <select name="owner_id" class="form-select form-select-sm">
                            <option value="">Unassigned</option>
                            <?php foreach($usersList as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $active_stage['owner_id'] == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">APPROVER</label>
                        <select name="approver_id" class="form-select form-select-sm">
                            <option value="">None Needed</option>
                            <?php foreach($usersList as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $active_stage['approver_id'] == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">DEADLINE</label>
                        <input type="date" name="deadline" class="form-control form-control-sm" value="<?= $active_stage['deadline'] ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-save"></i></button>
                    </div>
                </form>

                <div class="row">
                    <!-- Checklists -->
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Checklist</h6>
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
                            <input type="hidden" name="stage_id" value="<?= $active_stage['id'] ?>">
                            <input type="text" name="title" class="form-control form-control-sm" placeholder="New checklist item..." required>
                            <button class="btn btn-sm btn-secondary"><i class="bi bi-plus"></i></button>
                        </form>
                    </div>

                    <!-- Documents -->
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Documents & Links</h6>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($documents as $doc): ?>
                                <li class="list-group-item px-0 py-2 border-0 d-flex align-items-center">
                                    <span class="badge bg-secondary bg-opacity-25 text-body me-2" style="font-size:0.6rem;"><?= h($doc['type']) ?></span>
                                    <a href="<?= h($doc['url']) ?>" target="_blank" class="text-decoration-none text-truncate d-inline-block" style="max-width: 180px;"><?= h($doc['title']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#addDocModal">
                            <i class="bi bi-link-45deg"></i> Add Link
                        </button>
                    </div>
                </div>

                <!-- Approval Section -->
                <?php if ($active_stage['status'] !== 'Approved' && $active_stage['id'] == $current_project_stage_id): ?>
                    <div class="mt-4 pt-4 border-top text-end">
                        <form method="POST" onsubmit="return confirm('Ready to approve and move to the next stage?');">
                            <input type="hidden" name="action" value="approve_stage">
                            <input type="hidden" name="stage_id" value="<?= $active_stage['id'] ?>">
                            <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-check-circle me-2"></i> Approve & Move to Next Stage
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Comments Sidebar -->
    <div class="col-lg-4">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header border-0 pb-0">
                <h6 class="fw-bold mb-0">Stage Discussion</h6>
            </div>
            <div class="card-body d-flex flex-column flex-grow-1" style="max-height: 500px;">
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
                                <div class="bg-secondary bg-opacity-10 p-2 rounded small text-body">
                                    <?= nl2br(h($c['comment'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" class="mt-auto">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="stage_id" value="<?= $active_stage['id'] ?>">
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
                <input type="hidden" name="stage_id" value="<?= $active_stage['id'] ?>">
                
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
        
        fetch('project_view.php?id=<?= $project_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=toggle_checklist&checklist_id=${id}&is_completed=${checked}`
        });
    });
});
</script>

<?php include 'footer.php'; ?>
