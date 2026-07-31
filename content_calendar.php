<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();

$clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name ASC")->fetchAll();
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $post_title = $_POST['post_title'];
            $platform = $_POST['platform'];
            $status = $_POST['status'];
            $post_date = $_POST['post_date'] ?: null;
            $assigned_to = $_POST['assigned_to'] ?: null;
            $caption = $_POST['caption'];
            $drive_link = $_POST['drive_link'];
            $client_id = $_POST['client_id'] ?: null;

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO content_calendar (post_title, platform, status, post_date, assigned_to, caption, drive_link, client_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$post_title, $platform, $status, $post_date, $assigned_to, $caption, $drive_link, $client_id]);
                $_SESSION['flash_success'] = "Content added to calendar.";
            } else if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE content_calendar SET post_title=?, platform=?, status=?, post_date=?, assigned_to=?, caption=?, drive_link=?, client_id=? WHERE id=?");
                $stmt->execute([$post_title, $platform, $status, $post_date, $assigned_to, $caption, $drive_link, $client_id, $id]);
                $_SESSION['flash_success'] = "Content updated.";
            }
            header("Location: content_calendar.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM content_calendar WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_success'] = "Content deleted.";
            header("Location: content_calendar.php");
            exit;
        }
    }
}

$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_platform = $_GET['platform'] ?? '';

$query = "SELECT cc.*, c.client_name, u.username as assigned_user FROM content_calendar cc LEFT JOIN clients c ON cc.client_id = c.id LEFT JOIN users u ON cc.assigned_to = u.id WHERE 1=1 ";
$params = [];

if ($search) {
    $query .= " AND cc.post_title LIKE ? ";
    $params[] = "%$search%";
}
if ($filter_status) {
    $query .= " AND cc.status = ? ";
    $params[] = $filter_status;
}
if ($filter_platform) {
    $query .= " AND cc.platform = ? ";
    $params[] = $filter_platform;
}
$query .= " ORDER BY cc.post_date DESC, cc.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Content Calendar</h3>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contentModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Post
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body bg-light rounded d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex w-100 gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search posts..." value="<?= h($search) ?>">
            <select name="status" class="form-select" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <?php foreach(['Draft', 'Scheduled', 'Posted'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="platform" class="form-select" style="max-width: 150px;">
                <option value="">All Platforms</option>
                <?php foreach(['IG', 'TikTok', 'LinkedIn'] as $p): ?>
                    <option value="<?= $p ?>" <?= $filter_platform === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="content_calendar.php" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Post Title</th>
                        <th>Client</th>
                        <th>Platform / Status</th>
                        <th>Post Date</th>
                        <th>Assigned To</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($posts)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No content found.</td></tr>
                    <?php else: ?>
                        <?php foreach($posts as $post): ?>
                        <tr>
                            <td class="ps-3 fw-bold">
                                <?= h($post['post_title']) ?>
                                <?php if($post['drive_link']): ?>
                                    <a href="<?= h($post['drive_link']) ?>" target="_blank" class="text-decoration-none ms-2" title="Drive Link"><i class="bi bi-folder-fill text-warning"></i></a>
                                <?php endif; ?>
                                <?php if($post['caption']): ?>
                                    <div class="small text-muted text-truncate mt-1" style="max-width: 250px;" title="<?= h($post['caption']) ?>">
                                        <?= h($post['caption']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($post['client_name'] ?? 'Internal') ?></td>
                            <td>
                                <div>
                                    <?php
                                        $icon = 'bi-instagram';
                                        if ($post['platform'] == 'TikTok') $icon = 'bi-tiktok';
                                        if ($post['platform'] == 'LinkedIn') $icon = 'bi-linkedin';
                                    ?>
                                    <i class="bi <?= $icon ?> me-1"></i> <?= h($post['platform']) ?>
                                </div>
                                <div class="mt-1">
                                    <?php
                                        $sc = 'bg-soft-secondary';
                                        if ($post['status'] == 'Scheduled') $sc = 'bg-soft-warning';
                                        if ($post['status'] == 'Posted') $sc = 'bg-soft-success';
                                    ?>
                                    <span class="badge <?= $sc ?>"><?= h($post['status']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="<?= strtotime($post['post_date']) < strtotime('today') && $post['status'] != 'Posted' ? 'text-danger fw-bold' : '' ?>">
                                    <?= h($post['post_date'] ?? 'TBD') ?>
                                </span>
                            </td>
                            <td><?= h($post['assigned_user'] ?? 'Unassigned') ?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editPost(<?= json_encode($post) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="contentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="contentModalTitle">Add Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="contentAction" value="add">
                <input type="hidden" name="id" id="contentId" value="">
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label text-muted small fw-bold">POST TITLE *</label>
                        <input type="text" name="post_title" id="contentTitle" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">CLIENT</label>
                        <select name="client_id" id="contentClient" class="form-select">
                            <option value="">Internal / None</option>
                            <?php foreach($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">PLATFORM</label>
                        <select name="platform" id="contentPlatform" class="form-select">
                            <option value="IG">IG</option>
                            <option value="TikTok">TikTok</option>
                            <option value="LinkedIn">LinkedIn</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">STATUS</label>
                        <select name="status" id="contentStatus" class="form-select">
                            <option value="Draft">Draft</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Posted">Posted</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">POST DATE</label>
                        <input type="date" name="post_date" id="contentDate" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">ASSIGNED TO</label>
                        <select name="assigned_to" id="contentAssigned" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">DRIVE LINK (ASSETS)</label>
                        <input type="url" name="drive_link" id="contentDrive" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-bold">CAPTION</label>
                        <textarea name="caption" id="contentCaption" class="form-control" rows="4"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Post</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('contentAction').value = 'add';
    document.getElementById('contentId').value = '';
    document.getElementById('contentModalTitle').innerText = 'Add Post';
    document.getElementById('contentTitle').value = '';
    document.getElementById('contentClient').value = '';
    document.getElementById('contentPlatform').value = 'IG';
    document.getElementById('contentStatus').value = 'Draft';
    document.getElementById('contentDate').value = '';
    document.getElementById('contentAssigned').value = '';
    document.getElementById('contentDrive').value = '';
    document.getElementById('contentCaption').value = '';
}

function editPost(post) {
    document.getElementById('contentAction').value = 'edit';
    document.getElementById('contentId').value = post.id;
    document.getElementById('contentModalTitle').innerText = 'Edit Post';
    document.getElementById('contentTitle').value = post.post_title;
    document.getElementById('contentClient').value = post.client_id || '';
    document.getElementById('contentPlatform').value = post.platform;
    document.getElementById('contentStatus').value = post.status;
    document.getElementById('contentDate').value = post.post_date;
    document.getElementById('contentAssigned').value = post.assigned_to || '';
    document.getElementById('contentDrive').value = post.drive_link;
    document.getElementById('contentCaption').value = post.caption;
    
    var modal = new bootstrap.Modal(document.getElementById('contentModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
