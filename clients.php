<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Handle Actions (Add/Edit Client)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add' && $isFounder) {
        $company = $_POST['company_name'];
        $contact = $_POST['contact_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $status = $_POST['status'] ?? 'active';
        
        $pdo->prepare("INSERT INTO clients (company_name, contact_name, email, phone, status) VALUES (?, ?, ?, ?, ?)")
            ->execute([$company, $contact, $email, $phone, $status]);
        
        logActivity($pdo, 'Created Client', 'Client', $pdo->lastInsertId());
        $_SESSION['flash_success'] = "Client added successfully.";
        header("Location: clients.php");
        exit;
    }
}

// Fetch Clients
// Founder sees all. Others see clients where they have tasks assigned.
$clientsSql = "SELECT c.*, 
    (SELECT COUNT(*) FROM tasks t WHERE t.client_id = c.id AND t.deleted_at IS NULL AND t.status != 'Completed') as pending_tasks 
    FROM clients c WHERE c.deleted_at IS NULL";

if (!$isFounder) {
    $clientsSql .= " AND c.id IN (SELECT DISTINCT client_id FROM tasks WHERE assigned_to IN ($visibleIdsStr) AND deleted_at IS NULL)";
}
$clientsSql .= " ORDER BY c.company_name ASC";
$clients = $pdo->query($clientsSql)->fetchAll();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Client Hub</h2>
            <?php if ($isFounder): ?>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="bi bi-plus-lg"></i> New Client
            </button>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if(empty($clients)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-buildings fs-1 d-block mb-3"></i>
                    <h5>No clients found</h5>
                </div>
            <?php else: ?>
                <?php foreach($clients as $client): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card">
                        <div class="card-body position-relative">
                            <h5 class="fw-bold mb-1"><?= h($client['company_name']) ?></h5>
                            <p class="text-muted small mb-3"><i class="bi bi-person"></i> <?= h($client['contact_name']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="badge bg-<?= $client['status'] == 'active' ? 'success' : 'secondary' ?>"><?= ucfirst(h($client['status'])) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-warning text-dark"><i class="bi bi-list-task"></i> <?= h($client['pending_tasks']) ?> Pending Tasks</span>
                                </div>
                            </div>
                            
                            <hr class="text-muted">
                            <a href="client_view.php?id=<?= h($client['id']) ?>" class="btn btn-sm btn-outline-primary w-100 fw-bold">Manage Work Command Center</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isFounder): ?>
<div class="modal fade" id="addClientModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">New Client</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Company Name</label>
            <input type="text" name="company_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Contact Name</label>
            <input type="text" name="contact_name" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-6 mb-3">
                <label class="form-label fw-bold small text-muted">Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary fw-bold">Save Client</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
.kpi-card { transition: transform 0.2s; }
.kpi-card:hover { transform: translateY(-3px); }
</style>
</body>
</html>
