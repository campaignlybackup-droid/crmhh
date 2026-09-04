<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once 'config.php';
// Define the external upload directory (outside public_html)
define('UPLOAD_DIR', realpath(__DIR__ . '/..') . '/crm_uploads/');

function ensureUploadDirExists($subdir = '') {
    $path = UPLOAD_DIR . $subdir;
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    return $path;
}

require_once __DIR__ . '/includes/auth.php';

// Authentication Helpers
// (isLoggedIn, requireLogin, getCurrentUserId, getCurrentUsername, getVisibleUserIds are now in auth.php)

function isSuperAdmin() {
    global $pdo;
    return isFounder($pdo);
}

function isManager() {
    global $pdo;
    return isManagerRole($pdo);
}

function isEmployee() {
    return isset($_SESSION['user_id']); 
}

function requireSuperAdmin() {
    requireLogin();
    global $pdo;
    if (!isFounder($pdo)) {
        $_SESSION['flash_error'] = "Unauthorized access. Super Admin only.";
        header("Location: dashboard.php");
        exit;
    }
}

function requireManager() {
    requireLogin();
    global $pdo;
    if (!isManagerRole($pdo)) {
        $_SESSION['flash_error'] = "Unauthorized access. Manager only.";
        header("Location: dashboard.php");
        exit;
    }
}

// Notification Helpers
function addNotification($pdo, $user_id, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$user_id, $message]);
}

function notifySuperAdmins($pdo, $message) {
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'superadmin'");
    $admins = $stmt->fetchAll();
    foreach ($admins as $admin) {
        addNotification($pdo, $admin['id'], $message);
    }
}

function getUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
}

function markNotificationsRead($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// Lead History Helper
function logLeadHistory($pdo, $lead_id, $action, $details = '') {
    $user_id = getCurrentUserId();
    $stmt = $pdo->prepare("INSERT INTO lead_history (lead_id, action, details, changed_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$lead_id, $action, $details, $user_id]);
    
    // Also log to global activity stream
    logActivity($pdo, "Lead $action", 'Lead', $lead_id, $details);
}

// Global Activity Logger
// (logActivity and h() are now in auth.php)
?>
