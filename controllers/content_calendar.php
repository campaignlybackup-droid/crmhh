<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'store') {
    csrf_check_or_die();
    $clientId = (int)($_POST['client_id'] ?? 0);
    $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $contentType = in_array($_POST['content_type'] ?? '', ['reel', 'post', 'carousel', 'story']) ? $_POST['content_type'] : 'reel';
    $postDate = !empty($_POST['post_date']) ? $_POST['post_date'] : date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $driveLink = trim($_POST['drive_link'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    if ($clientId && $title) {
        $newId = ContentCalendarModel::create($clientId, $serviceId, $subcategoryId, $postDate, $title, $content, $status, $assignedTo, $contentType, $driveLink);
        Flash::success(ucfirst($contentType) . ' "' . $title . '" added to calendar.');
    } else {
        Flash::error('Client and Title are required.');
    }

    $returnTo = $_POST['return_to'] ?? '';
    if ($returnTo === 'client' && $clientId) {
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
    }
    redirect(url('content_calendar', ['client_id' => $clientId ?: null]));
}

if ($action === 'update') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $postDate = !empty($_POST['post_date']) ? $_POST['post_date'] : date('Y-m-d');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $driveLink = trim($_POST['drive_link'] ?? '');
    $contentType = in_array($_POST['content_type'] ?? '', ['reel', 'post', 'carousel', 'story']) ? $_POST['content_type'] : 'reel';
    $status = $_POST['status'] ?? 'draft';
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    if ($id && $title) {
        ContentCalendarModel::update($id, $postDate, $title, $content, $status, $assignedTo, $contentType, $driveLink);
        Flash::success('Content updated.');
    }
    
    $returnTo = $_POST['return_to'] ?? '';
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($returnTo === 'client' && $clientId) {
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
    }
    redirect(url('content_calendar'));
}

if ($action === 'toggle_done') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $res = ContentCalendarModel::toggleStatus($id);

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }

    if ($res['success']) {
        if ($res['is_completed']) {
            Flash::success('✓ Content marked as published & completed! Client deliverables counter updated in real-time.');
        } else {
            Flash::info('Content status restored to scheduled.');
        }
    } else {
        Flash::error('Failed to update status.');
    }

    $returnTo = $_POST['return_to'] ?? '';
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($returnTo === 'client' && $clientId) {
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
    }
    redirect(url('content_calendar'));
}

if ($action === 'delete') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $clientId = (int)($_POST['client_id'] ?? 0);
    $returnTo = $_POST['return_to'] ?? '';

    if ($id) {
        ContentCalendarModel::delete($id);
        Flash::success('Content removed.');
    }

    if ($returnTo === 'client' && $clientId) {
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
    }
    redirect(url('content_calendar'));
}

if ($action === 'editor_check') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? 'passed';
    $notes = trim($_POST['rectification_notes'] ?? '');
    
    if ($decision === 'flag_issues' && !$notes) {
        Flash::error('Please provide details on what issues need to be rectified.');
    } else {
        ContentCalendarModel::editorCheck($id, $decision === 'passed', $notes);
        if ($decision === 'passed') {
            Flash::success('Content passed Editor Check and advanced to Manager Review (Manav / Manager).');
        } else {
            Flash::warning('Issues flagged and returned for rectification.');
        }
    }
    redirect(url('content_calendar'));
}

if ($action === 'manager_review') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? 'approve';
    $notes = trim($_POST['rectification_notes'] ?? '');

    if ($decision === 'flag_issues' && !$notes) {
        Flash::error('Please provide details on what issues need to be rectified.');
    } else {
        ContentCalendarModel::managerReview($id, $decision === 'approve', $notes);
        if ($decision === 'approve') {
            Flash::success('Content final approved and scheduled!');
        } else {
            Flash::warning('Content returned for issue rectification.');
        }
    }
    redirect(url('content_calendar'));
}

// -------------------------------------------------------------
// INDEX ACTION
// -------------------------------------------------------------
$viewClientId = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$viewAssignedTo = !empty($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : null;
$contentTypeFilter = !empty($_GET['type']) ? $_GET['type'] : null;
$viewMode = $_GET['view_mode'] ?? 'pipeline'; // 'pipeline' or 'by_client'

if (!Permission::has('calendar.view_all')) {
    $viewAssignedTo = Auth::id();
}

$posts = ContentCalendarModel::all($viewClientId, $viewAssignedTo, $contentTypeFilter);

// Count summaries
$totalReelsCount = count(array_filter($posts, fn($p) => ($p['content_type'] ?? 'reel') === 'reel'));
$totalPostsCount = count(array_filter($posts, fn($p) => in_array($p['content_type'] ?? '', ['post', 'carousel', 'story', 'static'], true)));
$publishedCount = count(array_filter($posts, fn($p) => in_array($p['status'], ['published', 'completed'], true)));

$clients = Database::all('SELECT id, name, company FROM clients WHERE deleted_at IS NULL ORDER BY name');
$services = ServiceModel::all();
$managers = UserModel::activeSelectList();

$clientsDeliverables = [];
if ($viewMode === 'by_client') {
    $clientsDeliverables = ContentCalendarModel::clientsWithDeliverables();
}

render_page(
    'content_calendar/index', 
    compact('posts', 'clients', 'services', 'managers', 'viewClientId', 'viewAssignedTo', 'contentTypeFilter', 'viewMode', 'totalReelsCount', 'totalPostsCount', 'publishedCount', 'clientsDeliverables'), 
    'Content Calendar'
);
