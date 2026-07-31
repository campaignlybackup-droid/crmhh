<?php
session_start();
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

// Authentication Helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        die("Unauthorized access.");
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? '';
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
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
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
function logActivity($pdo, $action, $entity_type = null, $entity_id = null, $details = '') {
    $user_id = getCurrentUserId();
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $details]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist yet to prevent breaking before update_db is run
    }
}

// Security Helper
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
