<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

// Fetch clients for dropdowns based on assignment (superadmin sees all, user sees their assigned clients)
$clientsQuery = "SELECT id, client_name FROM clients";
$clientParams = [];
if (!$isSuper) {
    $clientsQuery .= " WHERE assigned_to = ?";
    $clientParams[] = $user_id;
}
$clientsQuery .= " ORDER BY client_name ASC";
$stmtC = $pdo->prepare($clientsQuery);
$stmtC->execute($clientParams);
$clients = $stmtC->fetchAll();

$users = [];
if ($isSuper) {
    $users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
}

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
                        $stmt = $pdo->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
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
        } elseif ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $project_name = $_POST['project_name'];
            $status = $_POST['status'];
            $client_id = $_POST['client_id'] ?: null;
            $project_value = $_POST['project_value'] ?: 0.00;
            $shoot_date = $_POST['shoot_date'] ?: null;
            $delivery_date = $_POST['delivery_date'] ?: null;
            $drive_folder_url = $_POST['drive_folder_url'];
            $payment_status = $_POST['payment_status'];
            $total_videos_planned = $_POST['total_videos_planned'] ?: 0;
            $videos_shot = $_POST['videos_shot'] ?: 0;
            $videos_edited = $_POST['videos_edited'] ?: 0;
            $videos_uploaded = $_POST['videos_uploaded'] ?: 0;

            if ($isSuper) {
                $assigned_to = $_POST['assigned_to'] ?: null;
            } else {
                if ($action === 'add') {
                    $assigned_to = $user_id;
                } else {
                    $stmt = $pdo->prepare("SELECT assigned_to FROM projects WHERE id = ?");
                    $stmt->execute([$id]);
                    $assigned_to = $stmt->fetchColumn();
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO projects (project_name, status, client_id, project_value, shoot_date, delivery_date, drive_folder_url, payment_status, assigned_to, total_videos_planned, videos_shot, videos_edited, videos_uploaded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_name, $status, $client_id, $project_value, $shoot_date, $delivery_date, $drive_folder_url, $payment_status, $assigned_to, $total_videos_planned, $videos_shot, $videos_edited, $videos_uploaded]);
                $new_id = $pdo->lastInsertId();
                logActivity($pdo, 'Created Project', 'Project', $new_id, $project_name);
                
                if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                    addNotification($pdo, $assigned_to, "You have been assigned a new project: $project_name");
                }
                
                $_SESSION['flash_success'] = "Project added successfully.";
            } else if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT assigned_to FROM projects WHERE id = ?");
                $stmt->execute([$id]);
                $oldProject = $stmt->fetch();

                if ($isSuper || ($oldProject && $oldProject['assigned_to'] == $user_id)) {
                    $stmt = $pdo->prepare("UPDATE projects SET project_name=?, status=?, client_id=?, project_value=?, shoot_date=?, delivery_date=?, drive_folder_url=?, payment_status=?, assigned_to=?, total_videos_planned=?, videos_shot=?, videos_edited=?, videos_uploaded=? WHERE id=?");
                    $stmt->execute([$project_name, $status, $client_id, $project_value, $shoot_date, $delivery_date, $drive_folder_url, $payment_status, $assigned_to, $total_videos_planned, $videos_shot, $videos_edited, $videos_uploaded, $id]);
                    logActivity($pdo, 'Updated Project', 'Project', $id, "Status: $status");
                    
                    if ($isSuper && $assigned_to && $assigned_to != $oldProject['assigned_to']) {
                        addNotification($pdo, $assigned_to, "You have been assigned to project: $project_name");
                    }
                    $_SESSION['flash_success'] = "Project updated successfully.";
                } else {
                    $_SESSION['flash_error'] = "Unauthorized.";
                }
            }
            header("Location: projects.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT assigned_to FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();
            
            if ($isSuper || ($project && $project['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
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

$query = "SELECT p.*, c.client_name, u.username as assigned_user FROM projects p LEFT JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.assigned_to = u.id WHERE 1=1 ";
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

$all_statuses = ['Briefing', 'Pre-Production', 'Shoot', 'Post', 'Review', 'Delivered'];

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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Project
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2 flex-wrap">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="text" name="search" class="form-control" placeholder="Search projects..." value="<?= h($search) ?>" style="min-width: 150px; flex: 1;">
            <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>" title="Start Shoot Date" style="max-width: 150px;">
            <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>" title="End Shoot Date" style="max-width: 150px;">
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
        .kanban-column { background-color: var(--light-bg); border-radius: 10px; min-width: 300px; max-width: 300px; padding: 15px; flex-shrink: 0; border: 1px solid var(--border-color); }
        .kanban-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-color); }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: var(--primary-color); }
        .kanban-card-title { font-weight: 600; font-size: 1.05rem; margin-bottom: 5px; color: var(--text-main); }
        .kanban-card-subtitle { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px; }
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
                    <div class="kanban-card" onclick='editProject(<?= json_encode($project) ?>)'>
                        <div class="kanban-card-title"><?= h($project['project_name']) ?></div>
                        <div class="kanban-card-subtitle">
                            <i class="bi bi-person"></i> <?= h($project['client_name'] ?: 'No Client') ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 small">
                            <span><i class="bi bi-camera"></i> <?= h($project['shoot_date'] ?? 'TBD') ?></span>
                            <?php
                                $pc = 'bg-soft-secondary';
                                if ($project['payment_status'] == 'Paid in Full') $pc = 'bg-soft-success';
                                if ($project['payment_status'] == 'Unpaid') $pc = 'bg-soft-danger';
                            ?>
                            <span class="badge <?= $pc ?>"><?= h($project['payment_status']) ?></span>
                        </div>
                        <div class="mt-2 small text-muted">
                            <?php
                                $left_shoot = max(0, $project['total_videos_planned'] - $project['videos_shot']);
                                $to_edit = max(0, $project['videos_shot'] - $project['videos_edited']);
                                $left_upload = max(0, $project['videos_edited'] - $project['videos_uploaded']);
                            ?>
                            <div class="d-flex flex-wrap gap-1 mt-1" style="font-size: 0.75rem;">
                                <span class="badge bg-light text-dark border">Shot: <?= $project['videos_shot'] ?>/<?= $project['total_videos_planned'] ?></span>
                                <?php if($to_edit > 0): ?><span class="badge bg-light text-warning border" title="To Edit">Edit: <?= $to_edit ?></span><?php endif; ?>
                                <?php if($left_upload > 0): ?><span class="badge bg-light text-info border" title="Left to Upload">Upload: <?= $left_upload ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center small text-muted">
                            <span class="fw-bold text-success">AED <?= number_format($project['project_value']) ?></span>
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
                            <th>Status</th>
                            <th>Dates</th>
                            <th>Video Status</th>
                            <th>Value / Assignee</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($projects)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No projects found.</td></tr>
                        <?php else: ?>
                            <?php foreach($projects as $project): ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $project['id'] ?>"></td>
                                <td class="fw-bold">
                                    <?= h($project['project_name']) ?>
                                    <?php if($project['drive_folder_url']): ?>
                                        <a href="<?= h($project['drive_folder_url']) ?>" target="_blank" class="text-decoration-none ms-2"><i class="bi bi-folder-fill text-warning"></i></a>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($project['client_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                        $sc = 'bg-soft-primary';
                                        if ($project['status'] == 'Delivered') $sc = 'bg-soft-success';
                                        if ($project['status'] == 'Briefing') $sc = 'bg-soft-secondary';
                                        if ($project['status'] == 'Review') $sc = 'bg-soft-warning';
                                    ?>
                                    <span class="badge badge-status <?= $sc ?>"><?= h($project['status']) ?></span>
                                </td>
                                <td class="small">
                                    <div><span class="text-muted">Shoot:</span> <?= h($project['shoot_date'] ?? '-') ?></div>
                                    <div><span class="text-muted">Delivery:</span> <?= h($project['delivery_date'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <?php
                                        $left_shoot = max(0, $project['total_videos_planned'] - $project['videos_shot']);
                                        $to_edit = max(0, $project['videos_shot'] - $project['videos_edited']);
                                        $left_upload = max(0, $project['videos_edited'] - $project['videos_uploaded']);
                                    ?>
                                    <div class="small">
                                        <div class="text-muted mb-1">Planned: <strong><?= $project['total_videos_planned'] ?></strong></div>
                                        <div class="d-flex flex-wrap gap-1" style="font-size: 0.75rem;">
                                            <span class="badge bg-light text-dark border" title="Left to Shoot: <?= $left_shoot ?>">Shot: <?= $project['videos_shot'] ?></span>
                                            <span class="badge bg-light text-dark border" title="To Edit: <?= $to_edit ?>">Edit: <?= $project['videos_edited'] ?></span>
                                            <span class="badge bg-light text-dark border" title="Left to Upload: <?= $left_upload ?>">Up: <?= $project['videos_uploaded'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success small">AED <?= number_format($project['project_value'], 2) ?></div>
                                    <?php if ($isSuper): ?>
                                    <div class="text-muted small"><i class="bi bi-person-badge"></i> <?= h($project['assigned_user'] ?? 'Unassigned') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editProject(<?= json_encode($project) ?>)'><i class="bi bi-pencil"></i></button>
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

<!-- Add/Edit Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="projectModalTitle">Add Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="projectAction" value="add">
                <input type="hidden" name="id" id="projectId" value="">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label text-muted small fw-bold">PROJECT NAME *</label>
                        <input type="text" name="project_name" id="projectName" class="form-control" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">CLIENT</label>
                        <select name="client_id" id="projectClient" class="form-select">
                            <option value="">Select Client...</option>
                            <?php foreach($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">STATUS</label>
                        <select name="status" id="projectStatus" class="form-select">
                            <?php foreach($all_statuses as $s) echo "<option value=\"$s\">$s</option>"; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">PAYMENT STATUS</label>
                        <select name="payment_status" id="projectPayment" class="form-select">
                            <?php foreach(['Unpaid', '50% Received', 'Paid in Full'] as $p) echo "<option value=\"$p\">$p</option>"; ?>
                        </select>
                    </div>

                    <?php if ($isSuper): ?>
                    <div class="col-md-12">
                        <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                        <select name="assigned_to" id="projectAssigned" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">SHOOT DATES (e.g., Oct 12, Oct 15)</label>
                        <input type="text" name="shoot_date" id="projectShoot" class="form-control" placeholder="Multiple dates allowed">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">DELIVERY DATE</label>
                        <input type="date" name="delivery_date" id="projectDelivery" class="form-control">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">VIDEOS PLANNED</label>
                        <input type="number" name="total_videos_planned" id="projectPlanned" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">VIDEOS SHOT</label>
                        <input type="number" name="videos_shot" id="projectShot" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">VIDEOS EDITED</label>
                        <input type="number" name="videos_edited" id="projectEdited" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">VIDEOS UPLOADED</label>
                        <input type="number" name="videos_uploaded" id="projectUploaded" class="form-control" value="0" min="0">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">PROJECT VALUE (AED)</label>
                        <input type="number" step="0.01" name="project_value" id="projectValue" class="form-control" placeholder="0.00">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label text-muted small fw-bold">DRIVE FOLDER URL</label>
                        <input type="url" name="drive_folder_url" id="projectDrive" class="form-control">
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
function resetForm() {
    document.getElementById('projectAction').value = 'add';
    document.getElementById('projectId').value = '';
    document.getElementById('projectModalTitle').innerText = 'Add Project';
    document.getElementById('projectName').value = '';
    document.getElementById('projectStatus').value = 'Briefing';
    document.getElementById('projectClient').value = '';
    document.getElementById('projectValue').value = '';
    document.getElementById('projectShoot').value = '';
    document.getElementById('projectDelivery').value = '';
    document.getElementById('projectPlanned').value = '0';
    document.getElementById('projectShot').value = '0';
    document.getElementById('projectEdited').value = '0';
    document.getElementById('projectUploaded').value = '0';
    <?php if ($isSuper): ?>document.getElementById('projectAssigned').value = '';<?php endif; ?>
    document.getElementById('projectDrive').value = '';
    document.getElementById('projectPayment').value = 'Unpaid';
}

function editProject(project) {
    document.getElementById('projectAction').value = 'edit';
    document.getElementById('projectId').value = project.id;
    document.getElementById('projectModalTitle').innerText = 'Edit Project';
    document.getElementById('projectName').value = project.project_name;
    document.getElementById('projectStatus').value = project.status;
    document.getElementById('projectClient').value = project.client_id || '';
    document.getElementById('projectValue').value = project.project_value;
    document.getElementById('projectShoot').value = project.shoot_date;
    document.getElementById('projectDelivery').value = project.delivery_date;
    document.getElementById('projectPlanned').value = project.total_videos_planned;
    document.getElementById('projectShot').value = project.videos_shot;
    document.getElementById('projectEdited').value = project.videos_edited;
    document.getElementById('projectUploaded').value = project.videos_uploaded;
    <?php if ($isSuper): ?>document.getElementById('projectAssigned').value = project.assigned_to || '';<?php endif; ?>
    document.getElementById('projectDrive').value = project.drive_folder_url;
    document.getElementById('projectPayment').value = project.payment_status;
    
    var modal = new bootstrap.Modal(document.getElementById('projectModal'));
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
