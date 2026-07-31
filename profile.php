<?php
require_once 'functions.php';
requireLogin();

$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['flash_error'] = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['flash_error'] = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['flash_error'] = "Password must be at least 6 characters long.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($stmt->execute([$hash, $user_id])) {
            $_SESSION['flash_success'] = "Password updated successfully.";
            // Optionally log out the user after password change
            // header("Location: logout.php");
            // exit;
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

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white fw-bold">
                Change Password
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">NEW PASSWORD</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">CONFIRM NEW PASSWORD</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
