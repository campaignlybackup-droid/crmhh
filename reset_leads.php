<?php
require_once 'functions.php';
requireLogin();

if (!isSuperAdmin()) {
    die("Unauthorized. Only Super Admins can reset leads.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    try {
        // TRUNCATE empties the table and resets the auto-increment ID back to 1
        $pdo->exec("TRUNCATE TABLE leads");
        
        // Also clear the lead_history table to remove all history logs
        $pdo->exec("TRUNCATE TABLE lead_history");
        
        $_SESSION['flash_success'] = "All leads and their history have been permanently deleted.";
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error deleting leads: " . $e->getMessage();
    }
    
    header("Location: leads.php");
    exit;
}

include 'header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> DANGER ZONE: Delete All Leads
            </div>
            <div class="card-body text-center py-5">
                <h4 class="text-danger mb-3">Are you absolutely sure?</h4>
                <p class="text-muted mb-4">
                    This will permanently delete <strong>all leads</strong> and their entire history from the database. This action cannot be undone, and all Lead IDs will be reset to 1.
                </p>
                <form method="POST">
                    <button type="submit" name="confirm_reset" value="1" class="btn btn-danger btn-lg px-5">
                        Yes, Delete All Leads
                    </button>
                    <a href="leads.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
