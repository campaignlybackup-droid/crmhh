<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'store') {
    csrf_check_or_die();
    $clientId = (int)($_POST['client_id'] ?? 0);
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $postDate = $_POST['post_date'] ?? date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    if ($clientId && $serviceId && $title) {
        ContentCalendarModel::create($clientId, $serviceId, $subcategoryId, $postDate, $title, $content, $status, $assignedTo);
        Flash::success('Content added to calendar.');
    } else {
        Flash::error('Missing required fields.');
    }
    redirect(url('content_calendar'));
}

if ($action === 'update') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $postDate = $_POST['post_date'] ?? date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    if ($id && $title) {
        ContentCalendarModel::update($id, $postDate, $title, $content, $status, $assignedTo);
        Flash::success('Content updated.');
    }
    redirect(url('content_calendar'));
}

if ($action === 'delete') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        ContentCalendarModel::delete($id);
        Flash::success('Content removed.');
    }
    redirect(url('content_calendar'));
}

$viewClientId = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$viewAssignedTo = !empty($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : null;

if (!Permission::has('calendar.view_all')) {
    // If not admin, maybe restrict to only their assigned posts? 
    // Wait, let's just let them see all, or only their assigned.
    // For now, let's keep it simple: if not admin, only see own assignments.
    $viewAssignedTo = Auth::id();
}

$posts = ContentCalendarModel::all($viewClientId, $viewAssignedTo);

$clients = ClientModel::all();
$services = ServiceModel::all();
$managers = UserModel::activeSelectList();

render_page('content_calendar/index', compact('posts', 'clients', 'services', 'managers', 'viewClientId', 'viewAssignedTo'), 'Content Calendar');
