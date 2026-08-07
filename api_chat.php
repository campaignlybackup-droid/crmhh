<?php
require_once 'functions.php';
requireLogin();
header('Content-Type: application/json');

$user_id = getCurrentUserId();
$isManager = isManager();
$isSuper = isSuperAdmin();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // Fetch pinned messages first
    $stmtPinned = $pdo->query("SELECT c.*, u.username, u.role FROM chat_messages c JOIN users u ON c.user_id = u.id WHERE c.is_pinned = TRUE ORDER BY c.created_at ASC");
    $pinned = $stmtPinned->fetchAll();
    
    // Fetch normal messages
    $stmtMsg = $pdo->prepare("SELECT c.*, u.username, u.role FROM chat_messages c JOIN users u ON c.user_id = u.id WHERE c.id > ? ORDER BY c.created_at ASC LIMIT 100");
    $stmtMsg->execute([$last_id]);
    $messages = $stmtMsg->fetchAll();
    
    echo json_encode(['success' => true, 'pinned' => $pinned, 'messages' => $messages, 'current_user_id' => $user_id]);
    exit;
}

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
    $stmt->execute([$user_id, $message]);
    $msg_id = $pdo->lastInsertId();
    
    // Check for @mentions
    preg_match_all('/@([a-zA-Z0-9_]+)/', $message, $matches);
    if (!empty($matches[1])) {
        $mentioned = array_unique($matches[1]);
        $placeholders = str_repeat('?,', count($mentioned) - 1) . '?';
        
        $stmtUsers = $pdo->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
        $stmtUsers->execute($mentioned);
        $users = $stmtUsers->fetchAll();
        
        $current_username = getCurrentUsername();
        foreach ($users as $u) {
            // Don't notify self
            if ($u['id'] != $user_id) {
                addNotification($pdo, $u['id'], "$current_username mentioned you in Team Chat.");
            }
        }
    }
    
    echo json_encode(['success' => true, 'id' => $msg_id]);
    exit;
}

if ($action === 'pin' || $action === 'unpin') {
    if (!$isManager && !$isSuper) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $msg_id = (int)($_POST['id'] ?? 0);
    $is_pinned = ($action === 'pin') ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE chat_messages SET is_pinned = ? WHERE id = ?");
    $stmt->execute([$is_pinned, $msg_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
