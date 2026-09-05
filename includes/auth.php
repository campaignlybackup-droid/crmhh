<?php
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? 'User';
}

function getUserRoles($pdo, $user_id) {
    // Simplify: Just use the `role` column in the `users` table
    if ($user_id == ($_SESSION['user_id'] ?? 0)) {
        $role = $_SESSION['role'] ?? 'user';
    } else {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
    }
    
    if ($role === 'superadmin') return ['Founder'];
    if ($role === 'manager') return ['Manager'];
    return ['Editor'];
}

function hasRole($pdo, $user_id, $role_name) {
    $roles = getUserRoles($pdo, $user_id);
    return in_array($role_name, $roles);
}

function isFounder($pdo, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) return false;
    return hasRole($pdo, $user_id, 'Founder');
}

function isManagerRole($pdo, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) return false;
    return hasRole($pdo, $user_id, 'Manager') || isFounder($pdo, $user_id);
}

function getVisibleUserIds($pdo, $user_id) {
    if (isFounder($pdo, $user_id)) {
        return []; // Empty array means 'all' globally visible
    }
    
    // If manager, they can see users who report to them
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reporting_manager_id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $visible = [$user_id]; // Always see self
    foreach ($subordinates as $member_id) {
        if (!in_array($member_id, $visible)) {
            $visible[] = $member_id;
        }
    }
    
    return $visible;
}

function logActivity($pdo, $action, $entity_type, $entity_id, $old_val = null, $new_val = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $entity_type, $entity_id, $old_val, $new_val]);
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
