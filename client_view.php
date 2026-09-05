<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

$client_id = $_GET['id'] ?? null;
if (!$client_id) { header("Location: clients.php"); exit; }

// Fetch Client (and check visibility)
$clientSql = "SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($clientSql);
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    $_SESSION['flash_error'] = "Client not found.";
    header("Location: clients.php");
    exit;
}

// In a real enterprise system, a non-founder can only see this if they have tasks for this client.
if (!$isFounder) {
    $chk = $pdo->prepare("SELECT id FROM tasks WHERE client_id = ? AND assigned_to IN ($visibleIdsStr) AND deleted_at IS NULL LIMIT 1");
    $chk->execute([$client_id]);
    if (!$chk->fetchColumn()) {
        $_SESSION['flash_error'] = "403 Forbidden: You don't have access to this client's command center.";
        header("Location: clients.php");
        exit;
    }
}

// Handle Add Service / Deliverable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_service' && ($isFounder || isManagerRole($pdo, $user_id))) {
        $service_name = $_POST['service_name'];
        $pdo->prepare("INSERT INTO client_services (client_id, service_name) VALUES (?, ?)")->execute([$client_id, $service_name]);
        logActivity($pdo, "Added Service: $service_name", 'Client', $client_id);
        $_SESSION['flash_success'] = "Service added.";
    }
    
    if ($action === 'add_deliverable' && ($isFounder || isManagerRole($pdo, $user_id))) {
        $client_service_id = $_POST['client_service_id'];
        $desc = $_POST['description'];
        $qty = $_POST['required_quantity'];
        $pdo->prepare("INSERT INTO deliverables (client_service_id, description, required_quantity) VALUES (?, ?, ?)")->execute([$client_service_id, $desc, $qty]);
        logActivity($pdo, "Added Deliverable", 'Client', $client_id);
        $_SESSION['flash_success'] = "Deliverable added.";
    }
    
    header("Location: client_view.php?id=$client_id");
    exit;
}

// Fetch Services & Deliverables
$services = $pdo->prepare("SELECT * FROM client_services WHERE client_id = ?");
$services->execute([$client_id]);
$services = $services->fetchAll();

foreach ($services as &$svc) {
    $del = $pdo->prepare("SELECT * FROM deliverables WHERE client_service_id = ?");
    $del->execute([$svc['id']]);
    $svc['deliverables'] = $del->fetchAll();
}
unset($svc);

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($client['company_name']) ?> - Command Center</title>
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
            <div>
                <a href="clients.php" class="text-decoration-none text-muted mb-2 d-block"><i class="bi bi-arrow-left"></i> Back to Clients</a>
                <h2 class="fw-bold mb-0"><?= h($client['company_name']) ?> Command Center</h2>
            </div>
            <?php if ($isFounder || isManagerRole($pdo, $user_id)): ?>
            <button class="btn btn-dark fw-bold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                <i class="bi bi-plus-lg"></i> Add Service
            </button>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if(empty($services)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-box-seam fs-1 d-block mb-3"></i>
                    <h5>No services defined for this client.</h5>
                </div>
            <?php else: ?>
                <?php foreach($services as $svc): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-briefcase"></i> <?= h($svc['service_name']) ?></h5>
                            <?php if ($isFounder || isManagerRole($pdo, $user_id)): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="addDeliverable(<?= $svc['id'] ?>, '<?= h(addslashes($svc['service_name'])) ?>')">
                                <i class="bi bi-plus"></i> Deliverable
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if(empty($svc['deliverables'])): ?>
                                <p class="text-muted small">No deliverables mapped yet.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach($svc['deliverables'] as $del): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= h($del['description']) ?></strong>
                                            <div class="text-muted small">Required: <?= $del['required_quantity'] ?> | Completed: <?= $del['completed_quantity'] ?></div>
                                        </div>
                                        <a href="tasks.php?client_id=<?= $client_id ?>&deliverable_id=<?= $del['id'] ?>" class="btn btn-sm btn-light border">Tasks <i class="bi bi-arrow-right"></i></a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">New Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_service">
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Service Name (e.g., Social Media)</label>
            <input type="text" name="service_name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary fw-bold">Save Service</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="addDeliverableModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Add Deliverable for <span id="svcNameSpan" class="text-primary"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_deliverable">
        <input type="hidden" name="client_service_id" id="modalSvcId">
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Description (e.g., 20 Instagram Reels)</label>
            <input type="text" name="description" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold small text-muted">Required Quantity</label>
            <input type="number" name="required_quantity" class="form-control" value="1" min="1" required>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary fw-bold">Save Deliverable</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function addDeliverable(svcId, svcName) {
    document.getElementById('modalSvcId').value = svcId;
    document.getElementById('svcNameSpan').innerText = svcName;
    new bootstrap.Modal(document.getElementById('addDeliverableModal')).show();
}
</script>
</body>
</html>
