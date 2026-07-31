<?php
require_once 'functions.php';
requireSuperAdmin(); // Only superadmins

// Fetch Leads
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM leads WHERE 1=1 ";
$params = [];

if ($search) {
    $query .= " AND (name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR notes LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Lead Master Sheet</h3>
        <p class="text-muted">Global search across all leads and history.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search any keyword..." value="<?= h($search) ?>">
            <button type="submit" class="btn btn-primary">Search All Leads</button>
            <a href="lead_master.php" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<?php if (empty($leads)): ?>
    <div class="alert alert-info">No leads found.</div>
<?php else: ?>
    <div class="accordion" id="leadsAccordion">
        <?php foreach($leads as $index => $lead): 
            $leadId = $lead['id'];
            // Fetch history for this lead
            $hStmt = $pdo->prepare("SELECT h.*, u.username FROM lead_history h LEFT JOIN users u ON h.changed_by = u.id WHERE h.lead_id = ? ORDER BY h.created_at DESC");
            $hStmt->execute([$leadId]);
            $history = $hStmt->fetchAll();
        ?>
        <div class="accordion-item mb-2 border-0 shadow-sm rounded">
            <h2 class="accordion-header" id="heading<?= $leadId ?>">
                <button class="accordion-button collapsed rounded fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $leadId ?>" aria-expanded="false" aria-controls="collapse<?= $leadId ?>">
                    <?= h($lead['name']) ?> &nbsp; 
                    <span class="badge bg-secondary ms-2"><?= h($lead['status']) ?></span>
                </button>
            </h2>
            <div id="collapse<?= $leadId ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $leadId ?>" data-bs-parent="#leadsAccordion">
                <div class="accordion-body bg-white border-top">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small">Lead Details</h6>
                            <table class="table table-sm table-borderless">
                                <tr><th width="120">Contact:</th><td><?= h($lead['contact_name']) ?></td></tr>
                                <tr><th>Email:</th><td><?= h($lead['email']) ?></td></tr>
                                <tr><th>Phone:</th><td><?= h($lead['phone']) ?></td></tr>
                                <tr><th>Instagram:</th><td><?= h($lead['instagram']) ?></td></tr>
                                <tr><th>Source:</th><td><?= h($lead['source']) ?></td></tr>
                                <tr><th>Industry:</th><td><?= h($lead['industry']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small">Pipeline Details</h6>
                            <table class="table table-sm table-borderless">
                                <tr><th width="120">Value:</th><td>$<?= number_format($lead['deal_value'], 2) ?></td></tr>
                                <tr><th>Next Action:</th><td><?= h($lead['next_action']) ?></td></tr>
                                <tr><th>Action Date:</th><td><?= h($lead['next_action_date']) ?></td></tr>
                                <tr><th>Created:</th><td><?= h($lead['created_at']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-12 mt-2">
                            <h6 class="fw-bold text-muted text-uppercase small">Notes</h6>
                            <p class="bg-light p-3 rounded small"><?= nl2br(h($lead['notes'])) ?></p>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold text-muted text-uppercase small mb-3 border-bottom pb-2">History Log</h6>
                    <?php if (empty($history)): ?>
                        <p class="text-muted small">No history recorded.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach($history as $record): ?>
                                <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-soft-primary text-primary me-2"><?= h($record['action']) ?></span>
                                        <?= h($record['details']) ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= h($record['created_at']) ?></div>
                                        <div class="fw-bold" style="font-size: 0.75rem;">by <?= h($record['username'] ?? 'System') ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
