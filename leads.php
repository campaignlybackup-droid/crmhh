<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

// Fetch projects and users for dropdowns
$projectsQuery = "SELECT id, project_name FROM projects";
$paramsProj = [];
if (!$isSuper) {
    $projectsQuery .= " WHERE assigned_to = ?";
    $paramsProj[] = $user_id;
}
$projectsQuery .= " ORDER BY project_name ASC";
$stmtProj = $pdo->prepare($projectsQuery);
$stmtProj->execute($paramsProj);
$projects = $stmtProj->fetchAll();

$users = [];
if ($isSuper) {
    $users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $status = $_POST['status'];
            $source = $_POST['source'];
            $industry = $_POST['industry'];
            $contact_name = $_POST['contact_name'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $instagram = $_POST['instagram'];
            $deal_value = $_POST['deal_value'] ?: 0.00;
            $next_action = $_POST['next_action'];
            $next_action_date = $_POST['next_action_date'] ?: null;
            $notes = $_POST['notes'];
            $project_id = $_POST['project_id'] ?: null;
            
            // Only superadmins can reassign. Regular users default to themselves or keep existing.
            if ($isSuper) {
                $assigned_to = $_POST['assigned_to'] ?: null;
            } else {
                if ($action === 'add') {
                    $assigned_to = $user_id;
                } else {
                    // Keep existing assignment for regular users
                    $stmt = $pdo->prepare("SELECT assigned_to FROM leads WHERE id = ?");
                    $stmt->execute([$id]);
                    $assigned_to = $stmt->fetchColumn();
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO leads (name, status, source, industry, contact_name, phone, email, instagram, deal_value, next_action, next_action_date, notes, project_id, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $status, $source, $industry, $contact_name, $phone, $email, $instagram, $deal_value, $next_action, $next_action_date, $notes, $project_id, $assigned_to]);
                $new_id = $pdo->lastInsertId();
                logLeadHistory($pdo, $new_id, 'Created', "Lead added via form.");
                
                if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                    addNotification($pdo, $assigned_to, "You have been assigned a new lead: $name");
                }
                
                $_SESSION['flash_success'] = "Lead added successfully.";
            } else if ($action === 'edit' && $id) {
                // Ensure user has access
                $oldStmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldLead = $oldStmt->fetch();

                if ($isSuper || ($oldLead && $oldLead['assigned_to'] == $user_id)) {
                    $stmt = $pdo->prepare("UPDATE leads SET name=?, status=?, source=?, industry=?, contact_name=?, phone=?, email=?, instagram=?, deal_value=?, next_action=?, next_action_date=?, notes=?, project_id=?, assigned_to=? WHERE id=?");
                    $stmt->execute([$name, $status, $source, $industry, $contact_name, $phone, $email, $instagram, $deal_value, $next_action, $next_action_date, $notes, $project_id, $assigned_to, $id]);
                    
                    $changes = [];
                    if ($oldLead['status'] !== $status) $changes[] = "Status changed to $status";
                    if ($oldLead['next_action_date'] !== $next_action_date) $changes[] = "Next action date updated";
                    $details = empty($changes) ? "Lead updated." : implode(", ", $changes);
                    
                    logLeadHistory($pdo, $id, 'Updated', $details);
                    
                    if ($isSuper && $assigned_to && $assigned_to != $oldLead['assigned_to']) {
                        addNotification($pdo, $assigned_to, "You have been assigned to lead: $name");
                    }
                    
                    $_SESSION['flash_success'] = "Lead updated successfully.";
                } else {
                    $_SESSION['flash_error'] = "Unauthorized.";
                }
            }
            header("Location: leads.php");
            exit;
        } elseif ($action === 'quick_status') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("SELECT assigned_to, name FROM leads WHERE id = ?");
            $stmt->execute([$id]);
            $lead = $stmt->fetch();
            
            if ($isSuper || ($lead && $lead['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                logLeadHistory($pdo, $id, 'Updated', "Status quick-changed to $status");
                $_SESSION['flash_success'] = "Lead status updated.";
            }
            header("Location: leads.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT assigned_to FROM leads WHERE id = ?");
            $stmt->execute([$id]);
            $lead = $stmt->fetch();
            
            if ($isSuper || ($lead && $lead['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_success'] = "Lead deleted.";
            } else {
                $_SESSION['flash_error'] = "Unauthorized.";
            }
            header("Location: leads.php");
            exit;
        }
    } elseif (isset($_FILES['import_file']) && $isSuper) {
        // ... (Keep existing import logic, assigning to superadmin by default or leaving unassigned)
        // For brevity, using standard logic.
        $fileInfo = pathinfo($_FILES['import_file']['name']);
        $ext = strtolower($fileInfo['extension']);
        $file = $_FILES['import_file']['tmp_name'];
        $count = 0;

        if ($ext === 'csv') {
            if (($handle = fopen($file, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $name = $data[0] ?? 'Unknown';
                    if (empty(trim($name))) continue;
                    $status = in_array($data[1] ?? '', ['New', 'Contacted', 'Warm', 'Proposal Sent', 'Negotiating', 'Won', 'Lost']) ? $data[1] : 'New';
                    $stmt = $pdo->prepare("INSERT INTO leads (name, status, source, industry, contact_name, phone, email, instagram, deal_value, next_action, next_action_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $status, $data[2] ?? '', $data[3] ?? '', $data[4] ?? '', $data[5] ?? '', $data[6] ?? '', $data[7] ?? '', floatval($data[8] ?? 0), $data[9] ?? '', $data[10] ?: null, $data[11] ?? '']);
                    logLeadHistory($pdo, $pdo->lastInsertId(), 'Imported', "Lead imported via CSV.");
                    $count++;
                }
                fclose($handle);
            }
        } elseif ($ext === 'xlsx') {
            require_once 'SimpleXLSX.php';
            if ($xlsx = SimpleXLSX::parse($file)) {
                $rows = $xlsx->rows();
                for ($i = 1; $i < count($rows); $i++) {
                    $data = $rows[$i];
                    $name = $data[0] ?? 'Unknown';
                    if (empty(trim($name))) continue;
                    $status = in_array($data[1] ?? '', ['New', 'Contacted', 'Warm', 'Proposal Sent', 'Negotiating', 'Won', 'Lost']) ? $data[1] : 'New';
                    $stmt = $pdo->prepare("INSERT INTO leads (name, status, source, industry, contact_name, phone, email, instagram, deal_value, next_action, next_action_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $status, $data[2] ?? '', $data[3] ?? '', $data[4] ?? '', $data[5] ?? '', $data[6] ?? '', $data[7] ?? '', floatval($data[8] ?? 0), $data[9] ?? '', $data[10] ?: null, $data[11] ?? '']);
                    logLeadHistory($pdo, $pdo->lastInsertId(), 'Imported', "Lead imported via XLSX.");
                    $count++;
                }
            } else {
                $_SESSION['flash_error'] = SimpleXLSX::parseError();
            }
        }
        if (!isset($_SESSION['flash_error'])) $_SESSION['flash_success'] = "$count leads imported.";
        header("Location: leads.php");
        exit;
    }
}

// Fetch Leads (with filters)
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_assignee = $_GET['assigned_to'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$view = $_GET['view'] ?? 'table';

$query = "SELECT l.*, p.project_name, u.username as assigned_user FROM leads l LEFT JOIN projects p ON l.project_id = p.id LEFT JOIN users u ON l.assigned_to = u.id WHERE 1=1 ";
$params = [];

if (!$isSuper) {
    $query .= " AND l.assigned_to = ? ";
    $params[] = $user_id;
} else if ($filter_assignee) {
    $query .= " AND l.assigned_to = ? ";
    $params[] = $filter_assignee;
}

if ($search) {
    $query .= " AND (l.name LIKE ? OR l.contact_name LIKE ? OR l.email LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND l.status = ? ";
    $params[] = $filter_status;
}
if ($start_date) {
    $query .= " AND l.created_at >= ? ";
    $params[] = $start_date . " 00:00:00";
}
if ($end_date) {
    $query .= " AND l.created_at <= ? ";
    $params[] = $end_date . " 23:59:59";
}
$query .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$all_statuses = ['New', 'Contacted', 'Warm', 'Proposal Sent', 'Negotiating', 'Won', 'Lost'];

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-4">
        <h3 class="fw-bold mb-0">Leads</h3>
    </div>
    <div class="col-md-8 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
        <div class="btn-group me-2" role="group">
            <a href="?view=table&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>" class="btn <?= $view === 'table' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-list-task"></i> Table</a>
            <a href="?view=board&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>" class="btn <?= $view === 'board' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-kanban"></i> Board</a>
        </div>
        <?php if ($isSuper): ?>
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload"></i> Import
        </button>
        <?php endif; ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leadModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Lead
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2 flex-wrap">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="text" name="search" class="form-control" placeholder="Search by name, contact or email..." value="<?= h($search) ?>" style="min-width: 150px; flex: 1;">
            <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>" title="Start Created Date" style="max-width: 150px;">
            <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>" title="End Created Date" style="max-width: 150px;">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach($all_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
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
            <a href="leads.php?view=<?= h($view) ?>" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<?php if ($view === 'board'): ?>
    <!-- KANBAN BOARD VIEW -->
    <style>
        .kanban-board { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 20px; min-height: 60vh; align-items: flex-start; }
        .kanban-column { background-color: var(--light-bg); border-radius: 10px; min-width: 320px; max-width: 320px; padding: 15px; flex-shrink: 0; border: 1px solid var(--border-color); }
        .kanban-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-color); }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: var(--primary-color); }
        .kanban-card-title { font-weight: 600; font-size: 1.05rem; margin-bottom: 5px; color: var(--text-main); }
        .quick-status-select { font-size: 0.75rem; padding: 2px 5px; height: auto; border-radius: 4px; display: inline-block; width: auto; }
    </style>
    
    <div class="kanban-board">
        <?php foreach ($all_statuses as $status_col): ?>
            <?php $col_leads = array_filter($leads, function($l) use ($status_col) { return $l['status'] === $status_col; }); ?>
            <div class="kanban-column">
                <h6 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                    <?= $status_col ?>
                    <span class="badge bg-secondary rounded-pill"><?= count($col_leads) ?></span>
                </h6>
                
                <?php foreach ($col_leads as $lead): ?>
                    <div class="kanban-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="kanban-card-title" onclick='editLead(<?= json_encode($lead) ?>)'><?= h($lead['name']) ?></div>
                            <?php if ($isSuper): ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width: 20px; height: 20px; font-size: 0.6rem;" title="<?= h($lead['assigned_user'] ?? 'Unassigned') ?>">
                                <?= strtoupper(substr($lead['assigned_user'] ?? '?', 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="small text-muted mb-2" onclick='editLead(<?= json_encode($lead) ?>)'>
                            <i class="bi bi-person"></i> <?= h($lead['contact_name'] ?: 'No Contact') ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-2 border-top pt-2">
                            <form method="POST" class="m-0" onchange="this.submit()">
                                <input type="hidden" name="action" value="quick_status">
                                <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                                <select name="status" class="form-select quick-status-select bg-light">
                                    <?php foreach($all_statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php if ($lead['deal_value'] > 0): ?>
                                <span class="fw-bold text-success small">AED <?= number_format($lead['deal_value']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- TABLE VIEW -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Name</th>
                            <th>Contact</th>
                            <th>Status (Quick Edit)</th>
                            <th>Value / Assignee</th>
                            <th>Next Action</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($leads)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No leads found.</td></tr>
                        <?php else: ?>
                            <?php foreach($leads as $lead): ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?= h($lead['name']) ?></td>
                                <td>
                                    <div class="small"><?= h($lead['contact_name']) ?></div>
                                    <div class="text-muted small"><?= h($lead['email']) ?></div>
                                </td>
                                <td>
                                    <form method="POST" class="m-0" onchange="this.submit()">
                                        <input type="hidden" name="action" value="quick_status">
                                        <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                            <?php foreach($all_statuses as $s): ?>
                                                <option value="<?= $s ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="fw-bold text-success small">AED <?= number_format($lead['deal_value'], 2) ?></div>
                                    <?php if ($isSuper): ?>
                                        <div class="small text-muted"><i class="bi bi-person-badge"></i> <?= h($lead['assigned_user'] ?? 'Unassigned') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small"><?= h($lead['next_action']) ?></div>
                                    <?php if($lead['next_action_date']): ?>
                                        <div class="text-muted small"><i class="bi bi-calendar"></i> <?= h($lead['next_action_date']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editLead(<?= json_encode($lead) ?>)'><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this lead?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $lead['id'] ?>">
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
<?php endif; ?>

<!-- Add/Edit Lead Modal -->
<div class="modal fade" id="leadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="leadModalTitle">Add Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="leadAction" value="add">
                <input type="hidden" name="id" id="leadId" value="">
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label text-muted small fw-bold">COMPANY/LEAD NAME *</label>
                        <input type="text" name="name" id="leadName" class="form-control" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">STATUS</label>
                        <select name="status" id="leadStatus" class="form-select">
                            <?php foreach($all_statuses as $s) echo "<option value=\"$s\">$s</option>"; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">DEAL VALUE (AED)</label>
                        <input type="number" step="0.01" name="deal_value" id="leadDealValue" class="form-control" placeholder="0.00">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">LINKED PROJECT</label>
                        <select name="project_id" id="leadProject" class="form-select">
                            <option value="">None</option>
                            <?php foreach($projects as $p) echo "<option value=\"{$p['id']}\">".h($p['project_name'])."</option>"; ?>
                        </select>
                    </div>
                    
                    <?php if ($isSuper): ?>
                    <div class="col-md-12">
                        <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                        <select name="assigned_to" id="leadAssigned" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">SOURCE</label>
                        <select name="source" id="leadSource" class="form-select">
                            <?php foreach(['Instagram DM', 'LinkedIn', 'Referral', 'WhatsApp', 'Cold Email', 'Walk-in'] as $s) echo "<option value=\"$s\">$s</option>"; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">INDUSTRY</label>
                        <select name="industry" id="leadIndustry" class="form-select">
                            <?php foreach(['F&B', 'Real Estate', 'Beauty', 'Automotive', 'Hospitality', 'Other'] as $i) echo "<option value=\"$i\">$i</option>"; ?>
                        </select>
                    </div>

                    <div class="col-12"><hr></div>
                    <h6 class="fw-bold mb-0">Contact Details</h6>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">CONTACT NAME</label>
                        <input type="text" name="contact_name" id="leadContact" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">PHONE / WHATSAPP</label>
                        <input type="text" name="phone" id="leadPhone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">EMAIL</label>
                        <input type="email" name="email" id="leadEmail" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">INSTAGRAM URL</label>
                        <input type="url" name="instagram" id="leadInsta" class="form-control">
                    </div>

                    <div class="col-12"><hr></div>
                    <h6 class="fw-bold mb-0">Follow Up</h6>

                    <div class="col-md-8">
                        <label class="form-label text-muted small fw-bold">NEXT ACTION</label>
                        <input type="text" name="next_action" id="leadNextAction" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">NEXT ACTION DATE</label>
                        <input type="date" name="next_action_date" id="leadNextDate" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small fw-bold">NOTES</label>
                        <textarea name="notes" id="leadNotes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Lead</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isSuper): ?>
<!-- CSV/XLSX Upload Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Import Leads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">CSV OR XLSX FILE</label>
                    <input type="file" name="import_file" accept=".csv, .xlsx" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function resetForm() {
    document.getElementById('leadAction').value = 'add';
    document.getElementById('leadId').value = '';
    document.getElementById('leadModalTitle').innerText = 'Add Lead';
    document.getElementById('leadName').value = '';
    document.getElementById('leadStatus').value = 'New';
    document.getElementById('leadDealValue').value = '';
    document.getElementById('leadProject').value = '';
    <?php if ($isSuper): ?>document.getElementById('leadAssigned').value = '';<?php endif; ?>
    document.getElementById('leadSource').value = 'Walk-in';
    document.getElementById('leadIndustry').value = 'Other';
    document.getElementById('leadContact').value = '';
    document.getElementById('leadPhone').value = '';
    document.getElementById('leadEmail').value = '';
    document.getElementById('leadInsta').value = '';
    document.getElementById('leadNextAction').value = '';
    document.getElementById('leadNextDate').value = '';
    document.getElementById('leadNotes').value = '';
}

function editLead(lead) {
    document.getElementById('leadAction').value = 'edit';
    document.getElementById('leadId').value = lead.id;
    document.getElementById('leadModalTitle').innerText = 'Edit Lead';
    document.getElementById('leadName').value = lead.name;
    document.getElementById('leadStatus').value = lead.status;
    document.getElementById('leadDealValue').value = lead.deal_value;
    document.getElementById('leadProject').value = lead.project_id || '';
    <?php if ($isSuper): ?>document.getElementById('leadAssigned').value = lead.assigned_to || '';<?php endif; ?>
    document.getElementById('leadSource').value = lead.source;
    document.getElementById('leadIndustry').value = lead.industry;
    document.getElementById('leadContact').value = lead.contact_name;
    document.getElementById('leadPhone').value = lead.phone;
    document.getElementById('leadEmail').value = lead.email;
    document.getElementById('leadInsta').value = lead.instagram;
    document.getElementById('leadNextAction').value = lead.next_action;
    document.getElementById('leadNextDate').value = lead.next_action_date;
    document.getElementById('leadNotes').value = lead.notes;
    
    var modal = new bootstrap.Modal(document.getElementById('leadModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
