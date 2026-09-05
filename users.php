<?php
require_once 'functions.php';
requireSuperAdmin();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $username = $_POST['username'];
            $role = $_POST['role'];
            $password = $_POST['password'];
            $phone = $_POST['phone'];
            $start_date = $_POST['start_date'] ?: null;
            $designation = $_POST['designation'] ?: null;
            $department = $_POST['department'] ?: null;
            $salary = $_POST['salary'] ?: 0;
            $reporting_manager_id = !empty($_POST['reporting_manager_id']) ? $_POST['reporting_manager_id'] : null;
            $status = $_POST['status'];

            if ($action === 'add') {
                if (empty($password)) {
                    $_SESSION['flash_error'] = "Password is required for new users.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, phone, start_date, designation, department, salary, reporting_manager_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $role, $phone, $start_date, $designation, $department, $salary, $reporting_manager_id, $status]);
                        $new_id = $pdo->lastInsertId();
                        $_SESSION['flash_success'] = "Team member added successfully.";
                    } catch(PDOException $e) {
                        $_SESSION['flash_error'] = "Username might already exist.";
                    }
                }
            } else if ($action === 'edit' && $id) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, password_hash=?, phone=?, start_date=?, designation=?, department=?, salary=?, reporting_manager_id=?, status=? WHERE id=?");
                    $stmt->execute([$username, $role, $hash, $phone, $start_date, $designation, $department, $salary, $reporting_manager_id, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, phone=?, start_date=?, designation=?, department=?, salary=?, reporting_manager_id=?, status=? WHERE id=?");
                    $stmt->execute([$username, $role, $phone, $start_date, $designation, $department, $salary, $reporting_manager_id, $status, $id]);
                }
                $_SESSION['flash_success'] = "Team member updated successfully.";
            }
            header("Location: users.php");
            exit;
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            if ($id == getCurrentUserId()) {
                $_SESSION['flash_error'] = "You cannot delete yourself.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_success'] = "User deleted.";
            }
            header("Location: users.php");
            exit;
        }
    }
}

// Fetch Users
try {
    $stmt = $pdo->query("SELECT id, username, role, phone, start_date, designation, department, salary, status, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $stmt = $pdo->query("SELECT id, username, role, phone, start_date, status, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
}

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">Manage Team / Users</h3>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
            <i class="bi bi-person-plus-fill"></i> Add Team Member
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Role / Status</th>
                        <th>Contact</th>
                        <th>Dept / Title</th>
                        <th>Start Date</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td class="ps-3 fw-bold"><?= h($user['username']) ?></td>
                        <td>
                            <?php if ($user['role'] == 'superadmin'): ?>
                                <span class="badge bg-danger">Superadmin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">User</span>
                            <?php endif; ?>
                            <div class="mt-1">
                                <?php
                                    $sc = 'bg-soft-primary';
                                    if($user['status'] == 'Off') $sc = 'bg-soft-danger';
                                    if($user['status'] == 'Freelance') $sc = 'bg-soft-warning';
                                ?>
                                <span class="badge <?= $sc ?>"><?= h($user['status']) ?></span>
                            </div>
                        </td>
                        <td class="small">
                            <div><i class="bi bi-telephone"></i> <?= h($user['phone'] ?? 'N/A') ?></div>
                        </td>
                        <td class="small">
                            <div class="fw-bold"><?= h($user['department'] ?? '-') ?></div>
                            <div class="text-muted"><?= h($user['designation'] ?? '-') ?></div>
                        </td>
                        <td class="small">
                            <div><?= h($user['start_date'] ?? 'N/A') ?></div>
                        </td>
                        <td class="text-end pe-3">
                            <a href="team_dashboard.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="View Performance Dashboard"><i class="bi bi-bar-chart-line"></i></a>
                            <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($user) ?>)' title="Edit User"><i class="bi bi-pencil"></i></button>
                            <?php if ($user['id'] != getCurrentUserId()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete User"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="userModalTitle">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="userAction" value="add">
                <input type="hidden" name="id" id="userId" value="">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">NAME / USERNAME *</label>
                        <input type="text" name="username" id="userName" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">SYSTEM ROLE</label>
                        <select name="role" id="userRole" class="form-select">
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">PHONE / WHATSAPP</label>
                        <input type="text" name="phone" id="userPhone" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">EMPLOYMENT STATUS</label>
                        <select name="status" id="userStatus" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Freelance">Freelance</option>
                            <option value="Off">Off</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">DESIGNATION</label>
                        <input type="text" name="designation" id="userDesignation" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">DEPARTMENT</label>
                        <input type="text" name="department" id="userDepartment" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">START DATE</label>
                        <input type="date" name="start_date" id="userStart" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold">REPORTS TO (MANAGER)</label>
                        <select name="reporting_manager_id" id="userManager" class="form-select">
                            <option value="">-- None (Top Level) --</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= h($u['username']) ?> (<?= h($u['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <hr>
                        <label class="form-label text-muted small fw-bold">LOGIN PASSWORD <span id="passReq" class="text-danger">*</span></label>
                        <input type="password" name="password" id="userPass" class="form-control">
                        <div class="form-text" id="passHelp">Leave blank to keep current password when editing.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Member</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('userAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('userModalTitle').innerText = 'Add Team Member';
    document.getElementById('userName').value = '';
    document.getElementById('userRole').value = 'user';
    document.getElementById('userPhone').value = '';
    document.getElementById('userStatus').value = 'Active';
    document.getElementById('userDesignation').value = '';
    document.getElementById('userDepartment').value = '';
    document.getElementById('userStart').value = '';
    document.getElementById('userManager').value = '';
    document.getElementById('userPass').value = '';
    document.getElementById('userPass').required = true;
    document.getElementById('passReq').style.display = 'inline';
}

function editUser(user) {
    document.getElementById('userAction').value = 'edit';
    document.getElementById('userId').value = user.id;
    document.getElementById('userModalTitle').innerText = 'Edit Team Member';
    document.getElementById('userName').value = user.username;
    document.getElementById('userRole').value = user.role;
    document.getElementById('userPhone').value = user.phone;
    document.getElementById('userStatus').value = user.status;
    document.getElementById('userDesignation').value = user.designation || '';
    document.getElementById('userDepartment').value = user.department || '';
    document.getElementById('userStart').value = user.start_date;
    document.getElementById('userManager').value = user.reporting_manager_id || '';
    document.getElementById('userPass').value = '';
    document.getElementById('userPass').required = false;
    document.getElementById('passReq').style.display = 'none';
    
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}
</script>

<?php include 'footer.php'; ?>
