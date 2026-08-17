<?php
require_once 'functions.php';
requireLogin();

$client_id = $_GET['id'] ?? null;
if (!$client_id) {
    $_SESSION['flash_error'] = "Invalid client ID.";
    header("Location: clients.php");
    exit;
}

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();

// Fetch Client Info
$stmt = $pdo->prepare("SELECT c.*, u.username as assigned_user FROM clients c LEFT JOIN users u ON c.assigned_to = u.id WHERE c.id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    $_SESSION['flash_error'] = "Client not found.";
    header("Location: clients.php");
    exit;
}

// Security Check
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = implode(',', $visibleIds);

$isLinked = false;
if (!$isSuper) {
    $pCheck = $pdo->prepare("SELECT 1 FROM projects WHERE client_id = ? AND (assigned_to IN ($visibleIdsStr) OR created_by = ?) LIMIT 1");
    $pCheck->execute([$client_id, $user_id]);
    if ($pCheck->fetchColumn()) {
        $isLinked = true;
    }
}

if (!$isSuper && !$isLinked && $client['assigned_to'] !== null && !in_array($client['assigned_to'], $visibleIds)) {
    $_SESSION['flash_error'] = "Unauthorized access to client profile.";
    header("Location: clients.php");
    exit;
}

// Fetch Linked Projects
$stmt = $pdo->prepare("SELECT p.*, u.username as assigned_user FROM projects p LEFT JOIN users u ON p.assigned_to = u.id WHERE p.client_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$client_id]);
$projects = $stmt->fetchAll();

// Fetch Linked Content
$stmt = $pdo->prepare("SELECT cc.*, u.username as assigned_user FROM content_calendar cc LEFT JOIN users u ON cc.assigned_to = u.id WHERE cc.client_id = ? ORDER BY cc.post_date DESC");
$stmt->execute([$client_id]);
$contents = $stmt->fetchAll();

// Fetch Linked Invoices
$invoices = [];
try {
    $stmt = $pdo->prepare("SELECT i.*, u.username as assigned_user FROM invoices i LEFT JOIN users u ON i.assigned_to = u.id WHERE i.client_id = ? ORDER BY i.created_at DESC");
    $stmt->execute([$client_id]);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {}

// Fetch Linked Proposals
$proposals = [];
try {
    $stmt = $pdo->prepare("SELECT p.*, u.username as assigned_user FROM proposals p LEFT JOIN users u ON p.assigned_to = u.id WHERE p.client_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$client_id]);
    $proposals = $stmt->fetchAll();
} catch (PDOException $e) {}

// Fetch Unified Recent Activity
$activityQuery = "
    SELECT 'Project' as type, project_name as title, status as extra, updated_at as date FROM projects WHERE client_id = ?
    UNION ALL
    SELECT 'Task' as type, t.task_title as title, t.status as extra, t.updated_at as date FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.client_id = ?
    UNION ALL
    SELECT 'Content' as type, post_title as title, status as extra, updated_at as date FROM content_calendar WHERE client_id = ?
    ORDER BY date DESC LIMIT 20
";
$stmt = $pdo->prepare($activityQuery);
$stmt->execute([$client_id, $client_id, $client_id]);
$activities = $stmt->fetchAll();

include 'header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <a href="clients.php" class="text-decoration-none text-muted mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Clients</a>
        <h2 class="fw-bold mb-0">
            <?= h($client['client_name']) ?>
            <?php
                $sc = 'bg-soft-secondary';
                if ($client['status'] == 'Active') $sc = 'bg-soft-success';
                if ($client['status'] == 'Churned') $sc = 'bg-soft-danger';
                if ($client['status'] == 'On Hold') $sc = 'bg-soft-warning';
            ?>
            <span class="badge <?= $sc ?> fs-6 ms-2 align-middle"><?= h($client['status']) ?></span>
        </h2>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted fw-bold small mb-3">CLIENT DETAILS</h6>
                
                <div class="mb-3">
                    <span class="d-block text-muted small">Primary Contact</span>
                    <span class="fw-bold"><?= h($client['primary_contact'] ?: 'N/A') ?></span>
                </div>
                
                <?php if ($isSuper): ?>
                <div class="mb-3">
                    <span class="d-block text-muted small">Total Billed</span>
                    <span class="fw-bold text-success fs-5">AED <?= number_format($client['total_billed'], 2) ?></span>
                </div>

                <div class="mb-3">
                    <span class="d-block text-muted small">Monthly Payment Date</span>
                    <span class="fw-bold text-primary"><?= h($client['monthly_payment_date'] ?: 'N/A') ?></span>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <span class="d-block text-muted small">Onboarding Date</span>
                    <span class="fw-bold"><?= h($client['onboarding_date'] ?: 'N/A') ?></span>
                </div>
                
                <div class="mb-3">
                    <span class="d-block text-muted small">Assigned Account Manager</span>
                    <span class="fw-bold"><i class="bi bi-person-badge text-primary"></i> <?= h($client['assigned_user'] ?: 'Unassigned') ?></span>
                </div>

                <hr>
                
                <div class="d-grid gap-2">
                    <?php if($client['drive_folder_url']): ?>
                        <a href="<?= h($client['drive_folder_url']) ?>" target="_blank" class="btn btn-outline-warning text-start">
                            <i class="bi bi-folder-fill me-2"></i> Open Drive Folder
                        </a>
                    <?php endif; ?>
                    <?php if($client['contract_file']): ?>
                        <a href="download.php?file=<?= urlencode($client['contract_file']) ?>" target="_blank" class="btn btn-outline-primary text-start">
                            <i class="bi bi-file-earmark-text me-2"></i> View Contract
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-5">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h6 class="text-muted fw-bold small mb-0">LINKED PROJECTS (<?= count($projects) ?>)</h6>
            </div>
            <div class="card-body">
                <?php if(empty($projects)): ?>
                    <p class="text-muted small">No projects linked to this client.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($projects as $p): ?>
                            <a href="projects.php?search=<?= urlencode($p['project_name']) ?>" class="list-group-item list-group-item-action px-0 border-0 mb-2 rounded bg-light p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= h($p['project_name']) ?></h6>
                                    <span class="badge bg-white text-dark border"><?= h($p['status']) ?></span>
                                </div>
                                <div class="small text-muted d-flex justify-content-between">
                                    <span>Value: AED <?= number_format($p['project_value']) ?></span>
                                    <span>Assignee: <?= h($p['assigned_user'] ?: 'None') ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h6 class="text-muted fw-bold small mb-0">RECENT ASSET ACTIVITY</h6>
            </div>
            <div class="card-body p-0">
                <?php if(empty($activities)): ?>
                    <div class="p-4 text-muted small">No recent activity recorded.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($activities as $act): ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="fw-bold" style="font-size: 0.9rem;">
                                        <?php
                                            $icon = 'bi-record-circle';
                                            if ($act['type'] == 'Project') $icon = 'bi-briefcase text-primary';
                                            if ($act['type'] == 'Task') $icon = 'bi-check2-square text-success';
                                            if ($act['type'] == 'Content') $icon = 'bi-instagram text-warning';
                                        ?>
                                        <i class="bi <?= $icon ?> me-2"></i> <?= h($act['title']) ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('M j, g:i A', strtotime($act['date'])) ?></small>
                                </div>
                                <div class="text-muted mt-1 small ms-4 ps-1">
                                    <?= h($act['type']) ?> updated to <strong><?= h($act['extra']) ?></strong>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
        <h6 class="text-muted fw-bold small mb-0">CONTENT CALENDAR DELIVERABLES</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Post Title</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th>Post Date</th>
                        <th>Assignee</th>
                        <th class="pe-3 text-end">Drive</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($contents)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-3">No content planned for this client.</td></tr>
                    <?php else: ?>
                        <?php foreach($contents as $c): ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?= h($c['post_title']) ?></td>
                                <td><?= h($c['platform']) ?></td>
                                <td>
                                    <?php
                                        $sc = 'bg-soft-secondary';
                                        if ($c['status'] == 'Scheduled') $sc = 'bg-soft-warning';
                                        if ($c['status'] == 'Posted') $sc = 'bg-soft-success';
                                    ?>
                                    <span class="badge <?= $sc ?>"><?= h($c['status']) ?></span>
                                </td>
                                <td><?= h($c['post_date'] ?: 'TBD') ?></td>
                                <td><?= h($c['assigned_user'] ?: 'Unassigned') ?></td>
                                <td class="pe-3 text-end">
                                    <?php if($c['drive_link']): ?>
                                        <a href="<?= h($c['drive_link']) ?>" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-folder-fill text-warning"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mb-4 g-4">
    <?php if ($isSuper): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h6 class="text-muted fw-bold small mb-0">INVOICES</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Invoice #</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($invoices)): ?>
                                <tr><td colspan="3" class="text-muted text-center py-3">No invoices.</td></tr>
                            <?php else: ?>
                                <?php foreach($invoices as $inv): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <?= h($inv['invoice_number']) ?>
                                            <?php if($inv['drive_link']): ?>
                                                <a href="<?= h($inv['drive_link']) ?>" target="_blank" class="ms-1"><i class="bi bi-link-45deg"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td>AED <?= number_format($inv['amount'], 2) ?></td>
                                        <td>
                                            <?php
                                                $sc = 'bg-soft-warning';
                                                if ($inv['status'] == 'Paid') $sc = 'bg-soft-success';
                                                if ($inv['status'] == 'Overdue') $sc = 'bg-soft-danger';
                                            ?>
                                            <span class="badge <?= $sc ?>"><?= h($inv['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h6 class="text-muted fw-bold small mb-0">PROPOSALS</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Name</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($proposals)): ?>
                                <tr><td colspan="3" class="text-muted text-center py-3">No proposals.</td></tr>
                            <?php else: ?>
                                <?php foreach($proposals as $prop): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <?= h($prop['proposal_name']) ?>
                                            <?php if($prop['drive_link']): ?>
                                                <a href="<?= h($prop['drive_link']) ?>" target="_blank" class="ms-1"><i class="bi bi-link-45deg"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td>AED <?= number_format($prop['amount'], 2) ?></td>
                                        <td>
                                            <?php
                                                $sc = 'bg-soft-secondary';
                                                if ($prop['status'] == 'Sent') $sc = 'bg-soft-primary';
                                                if ($prop['status'] == 'Accepted') $sc = 'bg-soft-success';
                                                if ($prop['status'] == 'Rejected') $sc = 'bg-soft-danger';
                                            ?>
                                            <span class="badge <?= $sc ?>"><?= h($prop['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
