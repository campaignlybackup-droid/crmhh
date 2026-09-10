<?php
Auth::requireLogin();

$action = $_GET['action'] ?? 'index';

if ($action === 'index') {
    try {
        $announcements = AnnouncementModel::all();
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), "Base table or view not found") !== false || strpos($e->getMessage(), "announcements' doesn't exist") !== false) {
            fatal_error("The 'announcements' table is missing. Please run the migration script by visiting: " . url('')."migrate_announcements_and_tz.php");
        }
        throw $e;
    }
    $canManage = Permission::has('announcements.manage');
    render_page('announcements/index', compact('announcements', 'canManage'), 'Announcements');
    exit;
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    Permission::require('announcements.manage');
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (!$title || !$content) {
        Flash::set('error', 'Title and content are required.');
        redirect(url('announcements'));
    }
    
    AnnouncementModel::create($title, $content, Auth::id());
    Flash::set('success', 'Announcement posted successfully.');
    redirect(url('announcements'));
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    Permission::require('announcements.manage');
    
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (!$id || !$title || !$content) {
        Flash::set('error', 'Title and content are required.');
    } else {
        AnnouncementModel::update($id, $title, $content);
        Flash::set('success', 'Announcement updated successfully.');
    }
    redirect(url('announcements'));
}

if ($action === 'delete') {
    csrf_check_or_die();
    Permission::require('announcements.manage');
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        AnnouncementModel::delete($id);
        Flash::set('success', 'Announcement deleted.');
    }
    redirect(url('announcements'));
}

http_response_code(404);
fatal_error('Not found');
