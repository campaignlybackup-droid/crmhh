<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

// Fetch clients for dropdowns based on assignment (superadmin sees all, user sees their assigned clients)
$clientsQuery = "SELECT id, client_name FROM clients WHERE deleted_at IS NULL";
$clientParams = [];
if (!$isSuper) {
    $clientsQuery .= " AND assigned_to = ?";
    $clientParams[] = $user_id;
}
$clientsQuery .= " ORDER BY client_name ASC";
$stmtC = $pdo->prepare($clientsQuery);
$stmtC->execute($clientParams);
$clients = $stmtC->fetchAll();

$users = [];
$templates = [];
if ($isSuper) {
    $users = $pdo->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username ASC")->fetchAll();
    $templates = $pdo->query("SELECT id, name FROM workflow_templates WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
}

$all_statuses = [
    'Onboarding', 'Creative Brief', 'Reference / Moodboard', 'Concept Approval', 
    'Pre Production', 'Production', 'Editing', 'Internal Review', 
    'Client Approval', 'Delivery', 'Case Study', 'Archive'
];

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
                    $checkStmt = $pdo->prepare("SELECT id FROM projects WHERE id IN ($placeholders) AND assigned_to = ?");
                    $checkParams = $selected_ids;
                    $checkParams[] = $user_id;
                    $checkStmt->execute($checkParams);
                    $selected_ids = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $params = $selected_ids;
                }

                if (!empty($selected_ids)) {
                    if ($bulk_action === 'delete') {
                        $stmt = $pdo->prepare("UPDATE projects SET deleted_at = NOW() WHERE id IN ($placeholders)");
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " projects deleted.";
                    } elseif ($bulk_action === 'status') {
                        $new_status = $_POST['bulk_status'] ?? '';
                        if ($new_status) {
                            $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id IN ($placeholders)");
                            array_unshift($params, $new_status);
                            $stmt->execute($params);
                            $_SESSION['flash_success'] = count($selected_ids) . " projects updated.";
                        }
                    } elseif ($bulk_action === 'assign' && $isSuper) {
                        $new_assignee = $_POST['bulk_assignee'] ?? null;
                        $stmt = $pdo->prepare("UPDATE projects SET assigned_to = ? WHERE id IN ($placeholders)");
                        array_unshift($params, $new_assignee ?: null);
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " projects reassigned.";
                    }
                }
            }
            header("Location: projects.php");
            exit;
        } elseif ($action === 'add') {
            $project_name = $_POST['project_name'];
            $client_id = $_POST['client_id'] ?: null;
            $project_value = $_POST['project_value'] ?? 0.00;
            $shoot_date = $_POST['shoot_date'] ?: null;
            $delivery_date = $_POST['delivery_date'] ?: null;
            $drive_folder_url = $_POST['drive_folder_url'];
            $payment_status = $_POST['payment_status'] ?? 'Unpaid';
            $total_videos_planned = $_POST['total_videos_planned'] ?: 0;
            $workflow_template_id = $_POST['workflow_template_id'] ?: null;
            
            $assigned_to = $isSuper ? ($_POST['assigned_to'] ?: null) : $user_id;

            $stmt = $pdo->prepare("INSERT INTO projects (project_name, status, client_id, project_value, shoot_date, delivery_date, drive_folder_url, payment_status, assigned_to, total_videos_planned, workflow_template_id) VALUES (?, 'Onboarding', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$project_name, $client_id, $project_value, $shoot_date, $delivery_date, $drive_folder_url, $payment_status, $assigned_to, $total_videos_planned, $workflow_template_id]);
            $new_id = $pdo->lastInsertId();
            
            // Seed the 12 workflow stages!
            $insertStage = $pdo->prepare("INSERT INTO project_stages (project_id, stage_name, status) VALUES (?, ?, ?)");
            foreach ($all_statuses as $index => $stageName) {
                // First stage is 'In Progress', rest are 'Pending'
                $stageStatus = ($index === 0) ? 'In Progress' : 'Pending';
                $insertStage->execute([$new_id, $stageName, $stageStatus]);
            }

            // ==========================================
            // TRIGGER WORKFLOW FOR FIRST STAGE
            // ==========================================
            if ($workflow_template_id) {
                $qTasks = $pdo->prepare("SELECT * FROM workflow_tasks WHERE template_id = ? AND trigger_stage_name = 'Onboarding'");
                $qTasks->execute([$workflow_template_id]);
                $auto_tasks = $qTasks->fetchAll();
                
                if (!empty($auto_tasks)) {
                    $insTask = $pdo->prepare("INSERT INTO tasks (task_name, status, assigned_to, due_date, priority, project_id) VALUES (?, 'To Do', ?, ?, 'Medium', ?)");
                    foreach ($auto_tasks as $at) {
                        $days_to_add = max(1, ceil($at['estimated_hours'] / 8));
                        $due_date = date('Y-m-d', strtotime("+$days_to_add days"));
                        $task_assignee = $at['default_assignee_id'] ?: $assigned_to;
                        $insTask->execute([$at['task_name'], $task_assignee, $due_date, $new_id]);
                        
                        if ($task_assignee) {
                            addNotification($pdo, $task_assignee, "Automated Task Assigned: " . $at['task_name'] . " (Project: " . $project_name . ")");
                        }
                    }
                }
            }

            logActivity($pdo, 'Created Project', 'Project', $new_id, $project_name);
            
            if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                addNotification($pdo, $assigned_to, "You have been assigned a new project: $project_name");
            }
            
            $_SESSION['flash_success'] = "Project added successfully.";
            header("Location: projects.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT assigned_to, project_name FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();
            
            if ($isSuper || ($project && $project['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("UPDATE projects SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                logActivity($pdo, 'Deleted Project', 'Project', $id, $project['project_name']);
                $_SESSION['flash_success'] = "Project deleted.";
            } else {
                $_SESSION['flash_error'] = "Unauthorized.";
            }
            header("Location: projects.php");
            exit;
        }
    }
}

$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_client = $_GET['client_id'] ?? '';
$filter_assignee = $_GET['assigned_to'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$view = $_GET['view'] ?? 'table';

$query = "SELECT p.*, c.client_name, u.username as assigned_user FROM projects p LEFT JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.assigned_to = u.id WHERE p.deleted_at IS NULL ";
$params = [];

if (!$isSuper) {
    $query .= " AND p.assigned_to = ? ";
    $params[] = $user_id;
} else if ($filter_assignee) {
    $query .= " AND p.assigned_to = ? ";
    $params[] = $filter_assignee;
}

if ($search) {
    $query .= " AND p.project_name LIKE ? ";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND p.status = ? ";
    $params[] = $filter_status;
}
if ($filter_client) {
    $query .= " AND p.client_id = ? ";
    $params[] = $filter_client;
}
if ($start_date) {
    $query .= " AND p.shoot_date >= ? ";
    $params[] = $start_date;
}
if ($end_date) {
    $query .= " AND p.shoot_date <= ? ";
    $params[] = $end_date;
}
$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-4">
        <h3 class="fw-bold mb-0">Projects</h3>
    </div>
    <div class="col-md-8 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
        <div class="btn-group me-2" role="group">
            <a href="?view=table&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>" class="btn <?= $view === 'table' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-list-task"></i> Table</a>
            <a href="?view=board&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>" class="btn <?= $view === 'board' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-kanban"></i> Timeline</a>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal">
            <i class="bi bi-plus-lg"></i> Add Project
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2 flex-wrap">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="text" name="search" class="form-control" placeholder="Search projects..." value="<?= h($search) ?>" style="min-width: 150px; flex: 1;">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach($all_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="client_id" class="form-select" style="max-width: 150px;">
                <option value="">All Clients</option>
                <?php foreach($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_client == $c['id']) ? 'selected' : '' ?>><?= h($c['client_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSuper): ?>
            <select name="assigned_to" class="form-select" style="max-width: 150px;">
                <option value="">All Assignees</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_assignee == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="projects.php?view=<?= h($view) ?>" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<?php if ($view === 'board'): ?>
    <style>
        .kanban-board { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 20px; min-height: 60vh; align-items: flex-start; }
        .kanban-column { background-color: var(--bs-secondary-bg); border-radius: 10px; min-width: 300px; max-width: 300px; padding: 15px; flex-shrink: 0; border: 1px solid var(--border-color); }
        .kanban-card { background: var(--bs-body-bg); border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-color); }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: var(--primary-color); }
        .kanban-card-title { font-weight: 600; font-size: 1.05rem; margin-bottom: 5px; color: var(--bs-body-color); }
        .kanban-card-subtitle { font-size: 0.8rem; color: var(--bs-secondary); margin-bottom: 10px; }
    </style>
    
    <div class="kanban-board">
        <?php foreach ($all_statuses as $status_col): ?>
            <?php 
                $col_projs = array_filter($projects, function($p) use ($status_col) { return $p['status'] === $status_col; });
            ?>
            <div class="kanban-column">
                <h6 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                    <?= $status_col ?>
                    <span class="badge bg-secondary rounded-pill"><?= count($col_projs) ?></span>
                </h6>
                
                <?php foreach ($col_projs as $project): ?>
                    <div class="kanban-card" onclick="window.location='project_view.php?id=<?= $project['id'] ?>'">
                        <div class="kanban-card-title"><?= h($project['project_name']) ?></div>
                        <div class="kanban-card-subtitle">
                            <i class="bi bi-person"></i> <?= h($project['client_name'] ?: 'No Client') ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top small text-muted">
                            <div class="d-flex align-items-center gap-2">
                                <form method="POST" class="m-0 d-inline" onsubmit="event.stopPropagation(); return confirm('Delete this project?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1 border-0" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                            <?php if ($isSuper): ?>
                            <span><i class="bi bi-person-badge"></i> <?= h($project['assigned_user'] ?: 'Unassigned') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- TABLE VIEW -->
    <form id="bulkForm" method="POST" action="projects.php">
        <input type="hidden" name="action" value="bulk">
        
        <div id="bulkActionBar" class="card mb-3 border-primary shadow-sm" style="display: none;">
            <div class="card-body bg-primary bg-opacity-10 py-2 d-flex align-items-center gap-3 flex-wrap">
                <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
                <select name="bulk_action" id="bulkActionSelect" class="form-select form-select-sm" style="width: auto;" onchange="toggleBulkOptions()">
                    <option value="">Choose bulk action...</option>
                    <option value="status">Change Status</option>
                    <?php if ($isSuper): ?><option value="assign">Reassign</option><?php endif; ?>
                    <option value="delete">Delete</option>
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
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40px;" class="ps-3"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Stage</th>
                            <th>Dates</th>
                            <th>Assignee</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($projects)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No projects found.</td></tr>
                        <?php else: ?>
                            <?php foreach($projects as $project): ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $project['id'] ?>"></td>
                                <td class="fw-bold">
                                    <a href="project_view.php?id=<?= $project['id'] ?>" class="text-decoration-none"><?= h($project['project_name']) ?></a>
                                    <?php if($project['drive_folder_url']): ?>
                                        <a href="<?= h($project['drive_folder_url']) ?>" target="_blank" class="text-decoration-none ms-2"><i class="bi bi-folder-fill text-warning"></i></a>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($project['client_name'] ?? 'N/A') ?></td>
                                <td><span class="badge bg-soft-primary badge-status"><?= h($project['status']) ?></span></td>
                                <td class="small">
                                    <div><span class="text-muted">Shoot:</span> <?= h($project['shoot_date'] ?? '-') ?></div>
                                    <div><span class="text-muted">Delivery:</span> <?= h($project['delivery_date'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div class="text-muted small"><i class="bi bi-person-badge"></i> <?= h($project['assigned_user'] ?? 'Unassigned') ?></div>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="project_view.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this project?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $project['id'] ?>">
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
    </form>
<?php endif; ?>

<!-- Add Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label text-muted small fw-bold">PROJECT NAME *</label>
                        <input type="text" name="project_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">CLIENT</label>
                        <select name="client_id" class="form-select">
                            <option value="">Select Client...</option>
                            <?php foreach($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($isSuper): ?>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">ASSIGN TO (PROJECT LEAD)</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">WORKFLOW TEMPLATE</label>
                        <select name="workflow_template_id" class="form-select">
                            <option value="">None (Manual)</option>
                            <?php foreach($templates as $t) echo "<option value=\"{$t['id']}\">".h($t['name'])."</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">SHOOT DATES (e.g., Oct 12, Oct 15)</label>
                        <input type="text" name="shoot_date" class="form-control" placeholder="Multiple dates allowed">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">DELIVERY DATE</label>
                        <input type="date" name="delivery_date" class="form-control">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label text-muted small fw-bold">DRIVE FOLDER URL</label>
                        <input type="url" name="drive_folder_url" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Project</button>
            </div>
        </form>
    </div>
</div>

<script>
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
