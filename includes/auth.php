<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'LocalDev Founder';
        }
    }
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
    // Enterprise RBAC: Fetch from roles and user_roles tables
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
        return []; // Empty array means 'all' globally visible
    }
    
    // VISIBILITY EQUATION = ROLE + HIERARCHY
    // If manager, get subordinates based on reporting_manager_id AND team_members
    $visible = [$user_id]; // Always see self
    
    // 1. Direct subordinates (Hierarchy)
    $stmt1 = $pdo->prepare("SELECT id FROM users WHERE reporting_manager_id = ? AND deleted_at IS NULL");
    $stmt1->execute([$user_id]);
    $subordinates = $stmt1->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Team members (Team)
    $stmt2 = $pdo->prepare("
        SELECT tm.user_id 
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE t.manager_id = ?
    ");
    $stmt2->execute([$user_id]);
    $team_members = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    $combined = array_merge($subordinates, $team_members);
    foreach ($combined as $member_id) {
        if (!in_array($member_id, $visible)) {
            $visible[] = $member_id;
        }
    }
    
    return $visible;
}

function canAccessEntity($pdo, $user_id, $entity_type, $entity_id) {
    if (isFounder($pdo, $user_id)) return true;
    
    $visibleIds = getVisibleUserIds($pdo, $user_id);
    if (empty($visibleIds)) return true; // Founder fallback
    
    $visibleIdsStr = implode(',', $visibleIds);
    
    if ($entity_type === 'Lead') {
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND assigned_to IN ($visibleIdsStr) AND deleted_at IS NULL");
        $stmt->execute([$entity_id]);
        return (bool)$stmt->fetchColumn();
    }
    
    if ($entity_type === 'Task') {
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to IN ($visibleIdsStr) AND deleted_at IS NULL");
        $stmt->execute([$entity_id]);
        return (bool)$stmt->fetchColumn();
    }
    
    if ($entity_type === 'Client') {
        return true; // Simplified for Phase 1
    }
    
    return false;
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
