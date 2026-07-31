<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

// Superadmin only (Optional: if we want only superadmin to manage proposals)
if (!$isSuper) {
    $_SESSION['flash_error'] = "Unauthorized access.";
    header("Location: dashboard.php");
    exit;
}

// Fetch clients for dropdowns based on assignment (superadmin sees all, user sees their assigned clients)
$clientsQuery = "SELECT id, client_name FROM clients ORDER BY client_name ASC";
$stmtC = $pdo->prepare($clientsQuery);
$stmtC->execute();
$clients = $stmtC->fetchAll();

$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $proposal_name = $_POST['proposal_name'];
            $client_id = $_POST['client_id'] ?: null;
            $amount = $_POST['amount'] ?: 0.00;
            $status = $_POST['status'];
            $drive_link = $_POST['drive_link'];
            $assigned_to = $_POST['assigned_to'] ?: null;

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO proposals (proposal_name, client_id, amount, status, drive_link, assigned_to) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$proposal_name, $client_id, $amount, $status, $drive_link, $assigned_to]);
                $new_id = $pdo->lastInsertId();
                logActivity($pdo, 'Created Proposal', 'Proposal', $new_id, $proposal_name);
                
                if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                    addNotification($pdo, $assigned_to, "You have been assigned a new proposal: $proposal_name");
                }
                
                $_SESSION['flash_success'] = "Proposal added successfully.";
            } else if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT assigned_to FROM proposals WHERE id = ?");
                $stmt->execute([$id]);
                $oldProposal = $stmt->fetch();

                $stmt = $pdo->prepare("UPDATE proposals SET proposal_name=?, client_id=?, amount=?, status=?, drive_link=?, assigned_to=? WHERE id=?");
                $stmt->execute([$proposal_name, $client_id, $amount, $status, $drive_link, $assigned_to, $id]);
                logActivity($pdo, 'Updated Proposal', 'Proposal', $id, "Status: $status");
                
                if ($isSuper && $assigned_to && $assigned_to != $oldProposal['assigned_to']) {
                    addNotification($pdo, $assigned_to, "You have been assigned to proposal: $proposal_name");
                }
                
                $_SESSION['flash_success'] = "Proposal updated successfully.";
            }
            header("Location: proposals.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM proposals WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, 'Deleted Proposal', 'Proposal', $id, "ID: $id");
            $_SESSION['flash_success'] = "Proposal deleted.";
            header("Location: proposals.php");
            exit;
        }
    }
}

$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_client = $_GET['client_id'] ?? '';
$filter_assignee = $_GET['assigned_to'] ?? '';

$query = "SELECT p.*, c.client_name, u.username as assigned_user FROM proposals p LEFT JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.assigned_to = u.id WHERE 1=1 ";
$params = [];

if ($filter_assignee) {
    $query .= " AND p.assigned_to = ? ";
    $params[] = $filter_assignee;
}
if ($search) {
    $query .= " AND p.proposal_name LIKE ? ";
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
$query .= " ORDER BY p.created_at DESC";

$proposals = [];
$db_error = false;
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $proposals = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = true;
}

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Proposals</h3>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proposalModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Proposal
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search by name..." value="<?= h($search) ?>">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach(['Draft', 'Sent', 'Accepted', 'Rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="client_id" class="form-select" style="max-width: 150px;">
                <option value="">All Clients</option>
                <?php foreach($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_client == $c['id']) ? 'selected' : '' ?>><?= h($c['client_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="assigned_to" class="form-select" style="max-width: 150px;">
                <option value="">All Assignees</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_assignee == $u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="proposals.php" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Proposal Name</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created / Assignee</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($proposals)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No proposals found.</td></tr>
                    <?php else: ?>
                        <?php foreach($proposals as $proposal): ?>
                        <tr>
                            <td class="ps-3 fw-bold">
                                <?= h($proposal['proposal_name']) ?>
                                <?php if($proposal['drive_link']): ?>
                                    <a href="<?= h($proposal['drive_link']) ?>" target="_blank" class="text-decoration-none ms-2"><i class="bi bi-link-45deg text-primary"></i></a>
                                <?php endif; ?>
                            </td>
                            <td><?= h($proposal['client_name'] ?? 'N/A') ?></td>
                            <td class="fw-bold text-success">AED <?= number_format($proposal['amount'], 2) ?></td>
                            <td>
                                <?php
                                    $sc = 'bg-soft-secondary';
                                    if ($proposal['status'] == 'Sent') $sc = 'bg-soft-primary';
                                    if ($proposal['status'] == 'Accepted') $sc = 'bg-soft-success';
                                    if ($proposal['status'] == 'Rejected') $sc = 'bg-soft-danger';
                                ?>
                                <span class="badge badge-status <?= $sc ?>"><?= h($proposal['status']) ?></span>
                            </td>
                            <td class="small">
                                <div><span class="text-muted">Date:</span> <?= date('Y-m-d', strtotime($proposal['created_at'])) ?></div>
                                <div class="text-muted mt-1"><i class="bi bi-person-badge"></i> <?= h($proposal['assigned_user'] ?? 'Unassigned') ?></div>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editProposal(<?= json_encode($proposal) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this proposal?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $proposal['id'] ?>">
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

<!-- Add/Edit Proposal Modal -->
<div class="modal fade" id="proposalModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="proposalModalTitle">Add Proposal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="proposalAction" value="add">
                <input type="hidden" name="id" id="proposalId" value="">
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PROPOSAL NAME *</label>
                    <input type="text" name="proposal_name" id="proposalName" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">CLIENT</label>
                    <select name="client_id" id="proposalClient" class="form-select">
                        <option value="">Select Client...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">AMOUNT (AED)</label>
                    <input type="number" step="0.01" name="amount" id="proposalAmount" class="form-control" placeholder="0.00">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">STATUS</label>
                    <select name="status" id="proposalStatus" class="form-select">
                        <option value="Draft">Draft</option>
                        <option value="Sent">Sent</option>
                        <option value="Accepted">Accepted</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                    <select name="assigned_to" id="proposalAssigned" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PROPOSAL LINK (DRIVE/PDF)</label>
                    <input type="url" name="drive_link" id="proposalDrive" class="form-control">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Proposal</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('proposalAction').value = 'add';
    document.getElementById('proposalId').value = '';
    document.getElementById('proposalModalTitle').innerText = 'Add Proposal';
    document.getElementById('proposalName').value = '';
    document.getElementById('proposalClient').value = '';
    document.getElementById('proposalAmount').value = '';
    document.getElementById('proposalStatus').value = 'Draft';
    document.getElementById('proposalAssigned').value = '';
    document.getElementById('proposalDrive').value = '';
}

function editProposal(proposal) {
    document.getElementById('proposalAction').value = 'edit';
    document.getElementById('proposalId').value = proposal.id;
    document.getElementById('proposalModalTitle').innerText = 'Edit Proposal';
    document.getElementById('proposalName').value = proposal.proposal_name;
    document.getElementById('proposalClient').value = proposal.client_id || '';
    document.getElementById('proposalAmount').value = proposal.amount;
    document.getElementById('proposalStatus').value = proposal.status;
    document.getElementById('proposalAssigned').value = proposal.assigned_to || '';
    document.getElementById('proposalDrive').value = proposal.drive_link;
    
    var modal = new bootstrap.Modal(document.getElementById('proposalModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
