<?php
require_once 'functions.php';
requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_template') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $stmt = $pdo->prepare("INSERT INTO workflow_templates (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $_SESSION['flash_success'] = "Template created.";
        header("Location: workflows.php");
        exit;
    } elseif ($action === 'delete_template') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("UPDATE workflow_templates SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = "Template deleted.";
        header("Location: workflows.php");
        exit;
    } elseif ($action === 'add_task') {
        $template_id = $_POST['template_id'];
        $trigger = $_POST['trigger_stage_name'];
        $task_name = $_POST['task_name'];
        $assignee = $_POST['default_assignee_id'] ?: null;
        $hours = $_POST['estimated_hours'] ?: 0;
        
        $stmt = $pdo->prepare("INSERT INTO workflow_tasks (template_id, trigger_stage_name, task_name, default_assignee_id, estimated_hours) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$template_id, $trigger, $task_name, $assignee, $hours]);
        $_SESSION['flash_success'] = "Automation rule added.";
        header("Location: workflows.php?id=$template_id");
        exit;
    } elseif ($action === 'delete_task') {
        $id = $_POST['id'];
        $template_id = $_POST['template_id'];
        $stmt = $pdo->prepare("DELETE FROM workflow_tasks WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = "Automation rule removed.";
        header("Location: workflows.php?id=$template_id");
        exit;
    }
}

$templates = $pdo->query("SELECT * FROM workflow_templates WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$active_template_id = $_GET['id'] ?? ($templates[0]['id'] ?? null);

$tasks = [];
$active_template = null;
if ($active_template_id) {
    foreach ($templates as $t) {
        if ($t['id'] == $active_template_id) $active_template = $t;
    }
    
    $stmt = $pdo->prepare("SELECT wt.*, u.username FROM workflow_tasks wt LEFT JOIN users u ON wt.default_assignee_id = u.id WHERE wt.template_id = ? ORDER BY wt.trigger_stage_name, wt.id");
    $stmt->execute([$active_template_id]);
    $raw_tasks = $stmt->fetchAll();
    
    foreach ($raw_tasks as $rt) {
        $tasks[$rt['trigger_stage_name']][] = $rt;
    }
}

$all_stages = [
    'Onboarding', 'Creative Brief', 'Reference / Moodboard', 'Concept Approval', 
    'Pre Production', 'Production', 'Editing', 'Internal Review', 
    'Client Approval', 'Delivery', 'Case Study', 'Archive'
];

$users = $pdo->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username")->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h3 class="fw-bold mb-0">Workflow Automations</h3>
        <p class="text-muted small mb-0">Define which tasks are automatically created when a project reaches a specific stage.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="bi bi-plus-lg"></i> New Template
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Templates Sidebar -->
    <div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
            <div class="card-header bg-light border-0">
                <h6 class="fw-bold mb-0 text-uppercase small text-muted">Templates</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if(empty($templates)): ?>
                    <div class="list-group-item text-muted small py-3">No templates created.</div>
                <?php else: ?>
                    <?php foreach($templates as $t): ?>
                        <a href="workflows.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action <?= $t['id'] == $active_template_id ? 'active fw-bold' : '' ?>">
                            <?= h($t['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-8 col-lg-9">
        <?php if ($active_template): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><?= h($active_template['name']) ?></h4>
                        <p class="text-muted small mb-0"><?= h($active_template['description']) ?></p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="bi bi-plus"></i> Add Automation Rule
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this entire template?');">
                            <input type="hidden" name="action" value="delete_template">
                            <input type="hidden" name="id" value="<?= $active_template['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-robot fs-1 text-muted mb-3 d-block"></i>
                        <h5>No automations yet</h5>
                        <p class="text-muted small">Click "Add Automation Rule" to define what happens when a project reaches a stage.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($all_stages as $stage): ?>
                    <?php if (isset($tasks[$stage])): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-light border-0 d-flex align-items-center">
                                <span class="badge bg-secondary me-2">Trigger</span>
                                <h6 class="fw-bold mb-0">When Project reaches "<?= h($stage) ?>"</h6>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($tasks[$stage] as $tsk): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div>
                                                <strong class="d-block mb-1"><?= h($tsk['task_name']) ?></strong>
                                                <div class="text-muted small">
                                                    <i class="bi bi-person me-1"></i> Assign to: <strong><?= h($tsk['username'] ?? 'Project Lead') ?></strong> &nbsp;|&nbsp;
                                                    <i class="bi bi-clock me-1"></i> Est. <?= $tsk['estimated_hours'] ?> hrs
                                                </div>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Remove this rule?');">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="id" value="<?= $tsk['id'] ?>">
                                                <input type="hidden" name="template_id" value="<?= $active_template['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info border-0">Select or create a template to begin building automations.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">New Workflow Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_template">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TEMPLATE NAME</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Standard Video Shoot" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DESCRIPTION</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100">Create Template</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Task Modal -->
<?php if ($active_template): ?>
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Automation Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" name="template_id" value="<?= $active_template['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TRIGGER STAGE</label>
                    <select name="trigger_stage_name" class="form-select" required>
                        <?php foreach($all_stages as $s) echo "<option value=\"$s\">$s</option>"; ?>
                    </select>
                    <div class="form-text">When a project enters this stage, the task will be generated.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">TASK NAME</label>
                    <input type="text" name="task_name" class="form-control" placeholder="e.g. Write Creative Brief" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted">DEFAULT ASSIGNEE</label>
                        <select name="default_assignee_id" class="form-select">
                            <option value="">(Assign to Project Lead)</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted">ESTIMATED HOURS</label>
                        <input type="number" name="estimated_hours" class="form-control" value="0" min="0">
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="submit" class="btn btn-primary w-100">Add Rule</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
