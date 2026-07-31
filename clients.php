<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

$users = [];
if ($isSuper) {
    $users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $client_name = $_POST['client_name'];
            $status = $_POST['status'];
            $primary_contact = $_POST['primary_contact'];
            $total_billed = $_POST['total_billed'] ?: 0.00;
            $drive_folder_url = $_POST['drive_folder_url'];
            $onboarding_date = $_POST['onboarding_date'] ?: null;
            
            if ($isSuper) {
                $assigned_to = $_POST['assigned_to'] ?: null;
            } else {
                if ($action === 'add') {
                    $assigned_to = $user_id;
                } else {
                    $stmt = $pdo->prepare("SELECT assigned_to FROM clients WHERE id = ?");
                    $stmt->execute([$id]);
                    $assigned_to = $stmt->fetchColumn();
                }
            }

            // Handle File Upload for Contract
            $contract_file = null;
            if (isset($_FILES['contract']) && $_FILES['contract']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/contracts/';
                $file_name = time() . '_' . basename($_FILES['contract']['name']);
                $target_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['contract']['tmp_name'], $target_path)) {
                    $contract_file = $target_path;
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO clients (client_name, status, primary_contact, total_billed, drive_folder_url, onboarding_date, contract_file, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_name, $status, $primary_contact, $total_billed, $drive_folder_url, $onboarding_date, $contract_file, $assigned_to]);
                
                if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                    addNotification($pdo, $assigned_to, "You have been assigned a new client: $client_name");
                }
                
                $_SESSION['flash_success'] = "Client added successfully.";
            } else if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT assigned_to FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                $oldClient = $stmt->fetch();

                if ($isSuper || ($oldClient && $oldClient['assigned_to'] == $user_id)) {
                    if ($contract_file) {
                        $stmt = $pdo->prepare("UPDATE clients SET client_name=?, status=?, primary_contact=?, total_billed=?, drive_folder_url=?, onboarding_date=?, contract_file=?, assigned_to=? WHERE id=?");
                        $stmt->execute([$client_name, $status, $primary_contact, $total_billed, $drive_folder_url, $onboarding_date, $contract_file, $assigned_to, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE clients SET client_name=?, status=?, primary_contact=?, total_billed=?, drive_folder_url=?, onboarding_date=?, assigned_to=? WHERE id=?");
                        $stmt->execute([$client_name, $status, $primary_contact, $total_billed, $drive_folder_url, $onboarding_date, $assigned_to, $id]);
                    }
                    
                    if ($isSuper && $assigned_to && $assigned_to != $oldClient['assigned_to']) {
                        addNotification($pdo, $assigned_to, "You have been assigned to client: $client_name");
                    }

                    $_SESSION['flash_success'] = "Client updated successfully.";
                } else {
                    $_SESSION['flash_error'] = "Unauthorized.";
                }
            }
            header("Location: clients.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT assigned_to FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch();
            
            if ($isSuper || ($client && $client['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_success'] = "Client deleted.";
            } else {
                $_SESSION['flash_error'] = "Unauthorized.";
            }
            header("Location: clients.php");
            exit;
        }
    }
}

// Fetch Clients
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_assignee = $_GET['assigned_to'] ?? '';

$query = "SELECT c.*, u.username as assigned_user FROM clients c LEFT JOIN users u ON c.assigned_to = u.id WHERE 1=1 ";
$params = [];

if (!$isSuper) {
    $query .= " AND c.assigned_to = ? ";
    $params[] = $user_id;
} else if ($filter_assignee) {
    $query .= " AND c.assigned_to = ? ";
    $params[] = $filter_assignee;
}

if ($search) {
    $query .= " AND (c.client_name LIKE ? OR c.primary_contact LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND c.status = ? ";
    $params[] = $filter_status;
}
$query .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Clients</h3>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Client
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search clients..." value="<?= h($search) ?>">
            <select name="status" class="form-select" style="max-width: 200px;">
                <option value="">All Statuses</option>
                <?php 
                $statuses = ['Active', 'Completed', 'On Hold', 'Churned'];
                foreach($statuses as $s) {
                    $selected = ($filter_status === $s) ? 'selected' : '';
                    echo "<option value=\"$s\" $selected>$s</option>";
                }
                ?>
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
            <a href="clients.php" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Client Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Billed / Assignee</th>
                        <th>Links / Files</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No clients found.</td></tr>
                    <?php else: ?>
                        <?php foreach($clients as $client): ?>
                        <tr>
                            <td class="ps-3 fw-bold">
                                <a href="client_profile.php?id=<?= $client['id'] ?>" class="text-decoration-none">
                                    <?= h($client['client_name']) ?> <i class="bi bi-box-arrow-up-right small ms-1 text-muted"></i>
                                </a>
                            </td>
                            <td><?= h($client['primary_contact']) ?></td>
                            <td>
                                <?php
                                    $sc = 'bg-soft-secondary';
                                    if ($client['status'] == 'Active') $sc = 'bg-soft-success';
                                    if ($client['status'] == 'Churned') $sc = 'bg-soft-danger';
                                    if ($client['status'] == 'On Hold') $sc = 'bg-soft-warning';
                                ?>
                                <span class="badge badge-status <?= $sc ?>"><?= h($client['status']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-success">AED <?= number_format($client['total_billed'], 2) ?></div>
                                <?php if ($isSuper): ?>
                                    <div class="small text-muted"><i class="bi bi-person-badge"></i> <?= h($client['assigned_user'] ?? 'Unassigned') ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if($client['drive_folder_url']): ?>
                                    <a href="<?= h($client['drive_folder_url']) ?>" target="_blank" class="btn btn-sm btn-light mb-1"><i class="bi bi-folder text-warning"></i> Drive</a>
                                <?php endif; ?>
                                <?php if($client['contract_file']): ?>
                                    <a href="<?= h($client['contract_file']) ?>" target="_blank" class="btn btn-sm btn-light mb-1"><i class="bi bi-file-earmark-text text-primary"></i> Contract</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="client_profile.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Profile"><i class="bi bi-eye"></i></a>
                                <button class="btn btn-sm btn-outline-primary" onclick='editClient(<?= json_encode($client) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this client?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $client['id'] ?>">
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

<!-- Add/Edit Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="clientModalTitle">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="clientAction" value="add">
                <input type="hidden" name="id" id="clientId" value="">
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">CLIENT NAME *</label>
                    <input type="text" name="client_name" id="clientName" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">STATUS</label>
                    <select name="status" id="clientStatus" class="form-select">
                        <?php foreach($statuses as $s) echo "<option value=\"$s\">$s</option>"; ?>
                    </select>
                </div>

                <?php if ($isSuper): ?>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                    <select name="assigned_to" id="clientAssigned" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PRIMARY CONTACT</label>
                    <input type="text" name="primary_contact" id="clientContact" class="form-control">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">TOTAL BILLED (AED)</label>
                    <input type="number" step="0.01" name="total_billed" id="clientBilled" class="form-control" placeholder="0.00">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">DRIVE FOLDER URL</label>
                    <input type="url" name="drive_folder_url" id="clientDrive" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">ONBOARDING DATE</label>
                    <input type="date" name="onboarding_date" id="clientDate" class="form-control">
                </div>

                <div class="mb-3 border-top pt-3">
                    <label class="form-label text-muted small fw-bold">CONTRACT FILE</label>
                    <input type="file" name="contract" class="form-control">
                    <div class="form-text">Upload a PDF/Doc for the client contract. Uploading a new one overwrites the old.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Client</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('clientAction').value = 'add';
    document.getElementById('clientId').value = '';
    document.getElementById('clientModalTitle').innerText = 'Add Client';
    document.getElementById('clientName').value = '';
    document.getElementById('clientStatus').value = 'Active';
    <?php if ($isSuper): ?>document.getElementById('clientAssigned').value = '';<?php endif; ?>
    document.getElementById('clientContact').value = '';
    document.getElementById('clientBilled').value = '';
    document.getElementById('clientDrive').value = '';
    document.getElementById('clientDate').value = '';
}

function editClient(client) {
    document.getElementById('clientAction').value = 'edit';
    document.getElementById('clientId').value = client.id;
    document.getElementById('clientModalTitle').innerText = 'Edit Client';
    document.getElementById('clientName').value = client.client_name;
    document.getElementById('clientStatus').value = client.status;
    <?php if ($isSuper): ?>document.getElementById('clientAssigned').value = client.assigned_to || '';<?php endif; ?>
    document.getElementById('clientContact').value = client.primary_contact;
    document.getElementById('clientBilled').value = client.total_billed;
    document.getElementById('clientDrive').value = client.drive_folder_url;
    document.getElementById('clientDate').value = client.onboarding_date;
    
    var modal = new bootstrap.Modal(document.getElementById('clientModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
