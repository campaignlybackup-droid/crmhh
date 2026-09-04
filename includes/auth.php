<?php
require_once __DIR__ . '/db.php';
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
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        return []; // Empty array means 'all'
    }
    
    // If manager, get their teams and team members
    $stmt = $pdo->prepare("
        SELECT tm.user_id 
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE t.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $team_members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $visible = [$user_id]; // Always see self
    foreach ($team_members as $member_id) {
        if (!in_array($member_id, $visible)) {
            $visible[] = $member_id;
        }
    }
    
    return $visible;
}

function logActivity($pdo, $action, $entity_type, $entity_id, $old_val = null, $new_val = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $old_val, $new_val]);
    } catch (Exception $e) {}
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
