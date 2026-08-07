<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$user_id = getCurrentUserId();
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = implode(',', $visibleIds);

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT a.*, u.username FROM activity_log a LEFT JOIN users u ON a.user_id = u.id ";
$countQuery = "SELECT COUNT(*) FROM activity_log a ";

if (!$isSuper) {
    $query .= " WHERE a.user_id IN ($visibleIdsStr) ";
    $countQuery .= " WHERE a.user_id IN ($visibleIdsStr) ";
}

$query .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";

// Fetch total count for pagination
$stmtCount = $pdo->query($countQuery);
$totalRows = $stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch activities
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-0 fw-bold">All Activity History</h2>
        <p class="text-muted mb-0">Complete historical log of system actions.</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0 ps-4">Date & Time</th>
                        <th class="border-0">User</th>
                        <th class="border-0">Action</th>
                        <th class="border-0">Entity</th>
                        <th class="border-0">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No activity found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($activities as $log): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="small fw-bold"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                    <div class="text-muted small"><?= date('g:i A', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-soft-primary text-primary d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.8rem;">
                                            <?= strtoupper(substr($log['username'] ?? '?', 0, 1)) ?>
                                        </div>
                                        <span class="fw-bold"><?= h($log['username'] ?? 'System') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-secondary text-secondary"><?= h($log['action_type'] ?? 'Action') ?></span>
                                </td>
                                <td>
                                    <?= h($log['entity_type']) ?>
                                </td>
                                <td>
                                    <?php if ($log['details']): ?>
                                        <span class="text-muted small"><?= h($log['details']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-0 py-3">
            <nav aria-label="Activity pagination">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php 
                    // Show a window of pages for large sets
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
