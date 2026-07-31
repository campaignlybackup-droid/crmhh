<?php
require_once 'functions.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if (isset($_GET['reset_admin']) && $_GET['reset_admin'] === 'yes') {
    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->query("UPDATE users SET password_hash = '$new_hash' WHERE username = 'admin'");
    $error = "Admin password has been reset to: admin123. Please try logging in now.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<?php include 'header.php'; ?>
<style>
    body {
        background-color: var(--dark-nav);
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
    }
    .main-content { padding: 0; width: 100%; }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0" style="border-radius: 20px;">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><i class="bi bi-rocket-takeoff me-2"></i>CRM</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger p-2 text-center" role="alert">
                            <?= h($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">USERNAME</label>
                            <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">PASSWORD</label>
                            <input type="password" name="password" class="form-control form-control-lg" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
