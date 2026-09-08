<?php
Auth::requireLogin();

$action = $_GET['action'] ?? 'index';

if ($action === 'index') {
    $announcements = AnnouncementModel::all();
    $canManage = Permission::has('announcements.manage');
    $pageTitle = 'Announcements';
    View::render('announcements/index', compact('announcements', 'canManage'));
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
