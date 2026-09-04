<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Fetch Clients
// A Founder sees all clients.
// A Manager sees clients assigned to them or their team members.
// Normal user sees clients assigned to them.
$clientsSql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id IN (SELECT id FROM projects p WHERE p.client_id = c.id) AND t.status != 'Done') as pending_tasks,
           u.username as assigned_user
    FROM clients c
    LEFT JOIN users u ON c.assigned_to = u.id
";

if (!$isFounder) {
    // If not founder, we need to show clients that have projects/tasks assigned to them
    // For simplicity, we show clients directly assigned to them or their team.
    $clientsSql .= " WHERE c.assigned_to IN ($visibleIdsStr) 
                     OR c.id IN (SELECT client_id FROM projects WHERE assigned_to IN ($visibleIdsStr))
                     OR c.id IN (SELECT p.client_id FROM projects p JOIN tasks t ON p.id = t.project_id JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.user_id IN ($visibleIdsStr))";
}

$clientsSql .= " GROUP BY c.id ORDER BY c.client_name ASC";
$stmt = $pdo->query($clientsSql);
$clients = $stmt ? $stmt->fetchAll() : [];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Clients</h3>
    <?php if ($isManager): ?>
    <button type="button" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i> New Client
    </button>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php if (empty($clients)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-people fs-1 d-block mb-3"></i>
            No clients found.
        </div>
    <?php else: ?>
        <?php foreach ($clients as $client): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm client-card hover-lift">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="fw-bold mb-0 text-truncate" title="<?= h($client['client_name']) ?>"><?= h($client['client_name']) ?></h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill"><?= h($client['status']) ?></span>
                        </div>
                        <div class="mb-3 text-muted small">
                            <i class="bi bi-person me-2"></i><?= h($client['primary_contact'] ?: 'No contact') ?><br>
                            <i class="bi bi-briefcase me-2"></i><?= $client['pending_tasks'] ?> pending tasks
                        </div>
                        <a href="client_profile.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-primary w-100 fw-bold">View Client</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.client-card { transition: transform 0.2s; }
.client-card:hover { transform: translateY(-5px); }
</style>

<?php include 'footer.php'; ?>
