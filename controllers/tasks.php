<?php

Permission::requireAny(['tasks.view', 'tasks.view_all']);

$action = $_GET['action'] ?? 'index';

switch ($action) {

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        $task = TaskModel::find($id);
        if (!$task) fatal_error('Task not found.');
        $timeline = ActivityModel::timeline('task', $id);
        $users = UserModel::activeSelectList();
        render_page('tasks/view', compact('task', 'timeline', 'users'), $task['title']);
        break;
    }

    case 'create': {
        Permission::require('tasks.create');
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name');
        $services = ServiceModel::all();
        $users = UserModel::activeSelectList();
        $preselectClientId = (int)($_GET['client_id'] ?? 0);
        render_page('tasks/form', ['task' => null, 'clients' => $clients, 'services' => $services, 'users' => $users, 'preselectClientId' => $preselectClientId], 'New Task');
        break;
    }

    case 'store': {
        Permission::require('tasks.create');
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('title', 'Title');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('tasks', ['action' => 'create'])); }
        $id = TaskModel::create($_POST);
        Flash::success('Task created.');
        redirect(url('tasks', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        Permission::require('tasks.edit');
        $task = TaskModel::find($id);
        if (!$task) fatal_error('Task not found.');
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name');
        $services = ServiceModel::all();
        render_page('tasks/form', compact('task', 'clients', 'services'), 'Edit Task');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        Permission::require('tasks.edit');
        csrf_check_or_die();
        TaskModel::update($id, $_POST);
        Flash::success('Task updated.');
        redirect(url('tasks', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        Permission::require('tasks.delete');
        csrf_check_or_die();
        TaskModel::softDelete($id);
        Flash::success('Task deleted.');
        redirect(url('tasks'));
        break;
    }

    case 'status': {
        $id = (int)($_POST['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        csrf_check_or_die();
        // Any user who can see the task and it's assigned to them (or has edit) may update its status.
        $task = TaskModel::find($id);
        if (!Permission::has('tasks.edit') && (int)$task['assigned_user_id'] !== Auth::id()) Permission::deny();
        TaskModel::changeStatus($id, $_POST['status'] ?? '');
        Flash::success('Status updated.');
        $returnTo = ($_POST['return'] ?? '') === 'list' ? url('tasks') : url('tasks', ['action' => 'view', 'id' => $id]);
        redirect($returnTo);
        break;
    }

    case 'reassign': {
        $id = (int)($_POST['id'] ?? 0);
        if (!TaskModel::canAccess($id)) Permission::deny();
        Permission::require('tasks.assign');
        csrf_check_or_die();
        $newUserId = (int)($_POST['assigned_user_id'] ?? 0);
        if ($newUserId) {
            TaskModel::reassign($id, $newUserId, $_POST['note'] ?? null);
            Flash::success('Task reassigned.');
        }
        redirect(url('tasks', ['action' => 'view', 'id' => $id]));
        break;
    }

    default: {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'assigned_user_id' => $_GET['assigned_user_id'] ?? '',
            'client_id' => $_GET['client_id'] ?? '',
            'priority' => $_GET['priority'] ?? '',
            'search' => trim($_GET['search'] ?? ''),
        ];
        $page = current_page_int();
        [$rows, $p] = TaskModel::paginate($page, 25, $filters);
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name');
        $users = UserModel::activeSelectList();
        render_page('tasks/list', compact('rows', 'p', 'filters', 'clients', 'users'), 'Tasks');
    }
}
