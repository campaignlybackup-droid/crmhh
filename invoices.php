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
                    $checkStmt = $pdo->prepare("SELECT id FROM invoices WHERE id IN ($placeholders) AND assigned_to = ?");
                    $checkParams = $selected_ids;
                    $checkParams[] = $user_id;
                    $checkStmt->execute($checkParams);
                    $selected_ids = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $params = $selected_ids;
                }

                if (!empty($selected_ids)) {
                    if ($bulk_action === 'delete') {
                        $stmt = $pdo->prepare("DELETE FROM invoices WHERE id IN ($placeholders)");
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " invoices deleted.";
                    } elseif ($bulk_action === 'status') {
                        $new_status = $_POST['bulk_status'] ?? '';
                        if ($new_status) {
                            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id IN ($placeholders)");
                            array_unshift($params, $new_status);
                            $stmt->execute($params);
                            $_SESSION['flash_success'] = count($selected_ids) . " invoices updated.";
                        }
                    } elseif ($bulk_action === 'assign' && $isSuper) {
                        $new_assignee = $_POST['bulk_assignee'] ?? null;
                        $stmt = $pdo->prepare("UPDATE invoices SET assigned_to = ? WHERE id IN ($placeholders)");
                        array_unshift($params, $new_assignee ?: null);
                        $stmt->execute($params);
                        $_SESSION['flash_success'] = count($selected_ids) . " invoices reassigned.";
                    }
                }
            }
            header("Location: invoices.php");
            exit;
        } elseif ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $invoice_number = $_POST['invoice_number'];
            $client_id = $_POST['client_id'] ?: null;
            $amount = $_POST['amount'] ?: 0.00;
            $status = $_POST['status'];
            $issue_date = $_POST['issue_date'] ?: null;
            $due_date = $_POST['due_date'] ?: null;
            $drive_link = $_POST['drive_link'];

            if ($isSuper) {
                $assigned_to = $_POST['assigned_to'] ?: null;
            } else {
                if ($action === 'add') {
                    $assigned_to = $user_id;
                } else {
                    $stmt = $pdo->prepare("SELECT assigned_to FROM invoices WHERE id = ?");
                    $stmt->execute([$id]);
                    $assigned_to = $stmt->fetchColumn();
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, amount, status, issue_date, due_date, drive_link, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$invoice_number, $client_id, $amount, $status, $issue_date, $due_date, $drive_link, $assigned_to]);
                $new_id = $pdo->lastInsertId();
                logActivity($pdo, 'Created Invoice', 'Invoice', $new_id, $invoice_number);
                
                if ($isSuper && $assigned_to && $assigned_to != $user_id) {
                    addNotification($pdo, $assigned_to, "You have been assigned a new invoice: $invoice_number");
                }
                
                $_SESSION['flash_success'] = "Invoice added successfully.";
            } else if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT assigned_to FROM invoices WHERE id = ?");
                $stmt->execute([$id]);
                $oldInvoice = $stmt->fetch();

                if ($isSuper || ($oldInvoice && $oldInvoice['assigned_to'] == $user_id)) {
                    $stmt = $pdo->prepare("UPDATE invoices SET invoice_number=?, client_id=?, amount=?, status=?, issue_date=?, due_date=?, drive_link=?, assigned_to=? WHERE id=?");
                    $stmt->execute([$invoice_number, $client_id, $amount, $status, $issue_date, $due_date, $drive_link, $assigned_to, $id]);
                    logActivity($pdo, 'Updated Invoice', 'Invoice', $id, "Status: $status");
                    
                    if ($isSuper && $assigned_to && $assigned_to != $oldInvoice['assigned_to']) {
                        addNotification($pdo, $assigned_to, "You have been assigned to invoice: $invoice_number");
                    }
                    
                    $_SESSION['flash_success'] = "Invoice updated successfully.";
                } else {
                    $_SESSION['flash_error'] = "Unauthorized.";
                }
            }
            header("Location: invoices.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT assigned_to FROM invoices WHERE id = ?");
            $stmt->execute([$id]);
            $invoice = $stmt->fetch();
            
            if ($isSuper || ($invoice && $invoice['assigned_to'] == $user_id)) {
                $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
                $stmt->execute([$id]);
                logActivity($pdo, 'Deleted Invoice', 'Invoice', $id, $invoice['invoice_number']);
                $_SESSION['flash_success'] = "Invoice deleted.";
            } else {
                $_SESSION['flash_error'] = "Unauthorized.";
            }
            header("Location: invoices.php");
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

$query = "SELECT i.*, c.client_name, u.username as assigned_user FROM invoices i LEFT JOIN clients c ON i.client_id = c.id LEFT JOIN users u ON i.assigned_to = u.id WHERE 1=1 ";
$params = [];

if (!$isSuper) {
    $query .= " AND i.assigned_to = ? ";
    $params[] = $user_id;
} else if ($filter_assignee) {
    $query .= " AND i.assigned_to = ? ";
    $params[] = $filter_assignee;
}

if ($search) {
    $query .= " AND i.invoice_number LIKE ? ";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND i.status = ? ";
    $params[] = $filter_status;
}
if ($filter_client) {
    $query .= " AND i.client_id = ? ";
    $params[] = $filter_client;
}
if ($start_date) {
    $query .= " AND i.issue_date >= ? ";
    $params[] = $start_date;
}
if ($end_date) {
    $query .= " AND i.issue_date <= ? ";
    $params[] = $end_date;
}
$query .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Invoices</h3>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Invoice
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2 flex-wrap">
            <input type="text" name="search" class="form-control" placeholder="Search by Invoice #..." value="<?= h($search) ?>" style="min-width: 150px; flex: 1;">
            <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>" title="Start Issue Date" style="max-width: 150px;">
            <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>" title="End Issue Date" style="max-width: 150px;">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach(['Unpaid', 'Paid', 'Overdue'] as $s): ?>
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
            <a href="invoices.php" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<form id="bulkForm" method="POST" action="invoices.php">
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
                <?php foreach(['Unpaid', 'Paid', 'Overdue'] as $s) echo "<option value=\"$s\">$s</option>"; ?>
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
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Dates / Assignee</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $invoice): ?>
                        <tr>
                            <td class="ps-3"><input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $invoice['id'] ?>"></td>
                            <td class="fw-bold">
                                <?= h($invoice['invoice_number']) ?>
                                <?php if($invoice['drive_link']): ?>
                                    <a href="<?= h($invoice['drive_link']) ?>" target="_blank" class="text-decoration-none ms-2"><i class="bi bi-link-45deg text-primary"></i></a>
                                <?php endif; ?>
                            </td>
                            <td><?= h($invoice['client_name'] ?? 'N/A') ?></td>
                            <td class="fw-bold text-success">AED <?= number_format($invoice['amount'], 2) ?></td>
                            <td>
                                <?php
                                    $sc = 'bg-soft-warning';
                                    if ($invoice['status'] == 'Paid') $sc = 'bg-soft-success';
                                    if ($invoice['status'] == 'Overdue') $sc = 'bg-soft-danger';
                                    
                                    // Auto-detect overdue visually
                                    if ($invoice['status'] != 'Paid' && strtotime($invoice['due_date']) < strtotime('today')) {
                                        $sc = 'bg-soft-danger';
                                        $invoice['status'] = 'Overdue';
                                    }
                                ?>
                                <span class="badge badge-status <?= $sc ?>"><?= h($invoice['status']) ?></span>
                            </td>
                            <td class="small">
                                <div><span class="text-muted">Issue:</span> <?= h($invoice['issue_date'] ?? '-') ?></div>
                                <div class="<?= $sc == 'bg-soft-danger' ? 'text-danger fw-bold' : '' ?>"><span class="text-muted">Due:</span> <?= h($invoice['due_date'] ?? '-') ?></div>
                                <?php if ($isSuper): ?>
                                    <div class="text-muted mt-1"><i class="bi bi-person-badge"></i> <?= h($invoice['assigned_user'] ?? 'Unassigned') ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editInvoice(<?= json_encode($invoice) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $invoice['id'] ?>">
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

<!-- Add/Edit Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="invoiceModalTitle">Add Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="invoiceAction" value="add">
                <input type="hidden" name="id" id="invoiceId" value="">
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">INVOICE NUMBER *</label>
                    <input type="text" name="invoice_number" id="invoiceNumber" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">CLIENT</label>
                    <select name="client_id" id="invoiceClient" class="form-select">
                        <option value="">Select Client...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">AMOUNT (AED)</label>
                    <input type="number" step="0.01" name="amount" id="invoiceAmount" class="form-control" placeholder="0.00">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">STATUS</label>
                    <select name="status" id="invoiceStatus" class="form-select">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Paid">Paid</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>

                <?php if ($isSuper): ?>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">ASSIGN TO</label>
                    <select name="assigned_to" id="invoiceAssigned" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach($users as $u) echo "<option value=\"{$u['id']}\">".h($u['username'])."</option>"; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label text-muted small fw-bold">ISSUE DATE</label>
                        <input type="date" name="issue_date" id="invoiceIssue" class="form-control">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label text-muted small fw-bold">DUE DATE</label>
                        <input type="date" name="due_date" id="invoiceDue" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">INVOICE LINK (DRIVE/PDF)</label>
                    <input type="url" name="drive_link" id="invoiceDrive" class="form-control">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Invoice</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('invoiceAction').value = 'add';
    document.getElementById('invoiceId').value = '';
    document.getElementById('invoiceModalTitle').innerText = 'Add Invoice';
    document.getElementById('invoiceNumber').value = '';
    document.getElementById('invoiceClient').value = '';
    document.getElementById('invoiceAmount').value = '';
    document.getElementById('invoiceStatus').value = 'Unpaid';
    <?php if ($isSuper): ?>document.getElementById('invoiceAssigned').value = '';<?php endif; ?>
    document.getElementById('invoiceIssue').value = '';
    document.getElementById('invoiceDue').value = '';
    document.getElementById('invoiceDrive').value = '';
}

function editInvoice(invoice) {
    document.getElementById('invoiceAction').value = 'edit';
    document.getElementById('invoiceId').value = invoice.id;
    document.getElementById('invoiceModalTitle').innerText = 'Edit Invoice';
    document.getElementById('invoiceNumber').value = invoice.invoice_number;
    document.getElementById('invoiceClient').value = invoice.client_id || '';
    document.getElementById('invoiceAmount').value = invoice.amount;
    document.getElementById('invoiceStatus').value = invoice.status;
    <?php if ($isSuper): ?>document.getElementById('invoiceAssigned').value = invoice.assigned_to || '';<?php endif; ?>
    document.getElementById('invoiceIssue').value = invoice.issue_date;
    document.getElementById('invoiceDue').value = invoice.due_date;
    document.getElementById('invoiceDrive').value = invoice.drive_link;
    
    var modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
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
