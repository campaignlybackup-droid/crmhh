<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'api_unread') {
    // Lightweight endpoint for polling notification count
    header('Content-Type: application/json');
    echo json_encode(['count' => Notifier::unreadCount(Auth::id())]);
    exit;
}

if ($action === 'mark_read') {
    csrf_check_or_die();
    Notifier::markAllRead(Auth::id());
    redirect(url('notifications'));
}

$page = current_page_int();
$perPage = 30;
$total = (int)Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [Auth::id()]);
$p = paginate_params($total, $page, $perPage);
$rows = Database::all(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
    [Auth::id()]
);
Notifier::markAllRead(Auth::id());

render_page('notifications/index', compact('rows', 'p'), 'Notifications');
