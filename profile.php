<?php
require_once 'functions.php';
requireLogin();

$user_id = getCurrentUserId();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['flash_error'] = "All fields are required.";
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $_SESSION['flash_error'] = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['flash_error'] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['flash_error'] = "Password must be at least 6 characters long.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($stmt->execute([$hash, $user_id])) {
            $_SESSION['flash_success'] = "Password updated successfully.";
        } else {
            $_SESSION['flash_error'] = "Error updating password.";
        }
    }
    header("Location: profile.php");
    exit;
}

include 'header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0">My Profile</h3>
    </div>
</div>

<div class="row g-4">
    <!-- User Info Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 80px; height: 80px; font-weight: 600; font-size: 2rem;">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-1"><?= h($user['username']) ?></h5>
                <p class="text-muted text-capitalize mb-3"><?= h($user['role']) ?></p>
                
                <ul class="list-group list-group-flush text-start">
                    <?php if ($user['designation']): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-bottom-0 pb-1">
                        <span class="text-muted small">Designation</span>
                        <span class="fw-bold small"><?= h($user['designation']) ?></span>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($user['department']): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-bottom-0 pb-1">
                        <span class="text-muted small">Department</span>
                        <span class="fw-bold small"><?= h($user['department']) ?></span>
                    </li>
                    <?php endif; ?>

                    <?php if ($user['phone']): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-bottom-0 pb-1">
                        <span class="text-muted small">Phone</span>
                        <span class="fw-bold small"><?= h($user['phone']) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Password Change Card -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 bg-transparent pb-0 mt-3">
                <h5 class="fw-bold mb-0">Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">CURRENT PASSWORD</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">NEW PASSWORD</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <div class="form-text small">Minimum 6 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">CONFIRM NEW PASSWORD</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-4">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
