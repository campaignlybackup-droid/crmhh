<?php

Permission::requireAny(['clients.view', 'clients.view_all']);

$action = $_GET['action'] ?? 'index';

switch ($action) {

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        if (!Permission::canAccessClient($id)) Permission::deny();
        // "Full" record access (contact details, all services, edit tools) is a
        // manager/founder-level privilege. A plain executor role that merely has
        // an assignment on this client (e.g. an Editor) is visible above via
        // canAccessClient(), but must only ever see their own scoped work.
        $fullAccess = Permission::hasAny(['clients.view_all', 'clients.edit', 'clients.manage_services']);
        $client = ClientModel::find($id);
        if (!$client) fatal_error('Client not found.');

        if ($fullAccess) {
            $services = ClientModel::servicesFor($id);
            foreach ($services as &$svc) {
                $svc['assignments'] = ClientModel::assignmentsFor((int)$svc['id']);
            }
            unset($svc);
        } else {
            $services = ClientModel::servicesForEmployee($id, Auth::id());
            if (empty($services)) Permission::deny();
        }
        $timeline = $fullAccess ? ActivityModel::timeline('client', $id) : [];
        $tasks = Database::all(
            "SELECT t.*, u.name AS assigned_name FROM tasks t LEFT JOIN users u ON u.id = t.assigned_user_id
             WHERE t.client_id = ? AND t.deleted_at IS NULL ORDER BY (t.deadline IS NULL), t.deadline ASC LIMIT 20",
            [$id]
        );
        $allServices = ServiceModel::all();
        $managers = UserModel::activeSelectList();
        render_page('clients/view', compact('client', 'services', 'timeline', 'tasks', 'fullAccess', 'allServices', 'managers'), $client['name']);
        break;
    }

    case 'create': {
        Permission::require('clients.create');
        render_page('clients/form', ['client' => null], 'New Client');
        break;
    }

    case 'store': {
        Permission::require('clients.create');
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Client name')->email('email', 'Email');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('clients', ['action' => 'create'])); }
        $id = ClientModel::create($_POST);
        Flash::success('Client created.');
        redirect(url('clients', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        if (!Permission::canAccessClient($id)) Permission::deny();
        Permission::require('clients.edit');
        $client = ClientModel::find($id);
        if (!$client) fatal_error('Client not found.');
        render_page('clients/form', compact('client'), 'Edit Client');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        if (!Permission::canAccessClient($id)) Permission::deny();
        Permission::require('clients.edit');
        csrf_check_or_die();
        ClientModel::update($id, $_POST);
        Flash::success('Client updated.');
        redirect(url('clients', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if (!Permission::canAccessClient($id)) Permission::deny();
        Permission::require('clients.delete');
        csrf_check_or_die();
        ClientModel::softDelete($id);
        Flash::success('Client deleted.');
        redirect(url('clients'));
        break;
    }

    case 'add_service': {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if (!Permission::canAccessClient($clientId)) Permission::deny();
        Permission::require('clients.manage_services');
        csrf_check_or_die();
        ClientModel::addService($clientId, (int)$_POST['service_id'], (int)$_POST['quantity_required'], $_POST['manager_id'] ?: null, $_POST['start_date'] ?: null, $_POST['end_date'] ?: null, $_POST['notes'] ?: null);
        Flash::success('Service added to client.');
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
        break;
    }

    case 'update_service': {
        $clientServiceId = (int)($_POST['client_service_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        if (!Permission::canAccessClient($clientId)) Permission::deny();
        Permission::require('clients.manage_services');
        csrf_check_or_die();
        ClientModel::updateService($clientServiceId, (int)$_POST['quantity_required'], $_POST['manager_id'] ?: null, $_POST['start_date'] ?: null, $_POST['end_date'] ?: null, $_POST['notes'] ?: null, $_POST['status'] ?: 'active');
        Flash::success('Requirement updated.');
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
        break;
    }

    case 'assign_employee': {
        $clientServiceId = (int)($_POST['client_service_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        if (!Permission::canAccessClient($clientId)) Permission::deny();
        Permission::requireAny(['clients.manage_services', 'clients.assign']);
        csrf_check_or_die();
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId) {
            ClientModel::assignEmployeeToService($clientServiceId, $userId, $_POST['quantity_assigned'] !== '' ? (int)$_POST['quantity_assigned'] : null);
            Flash::success('Employee assigned to this work.');
        }
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
        break;
    }

    case 'remove_assignment': {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        if (!Permission::canAccessClient($clientId)) Permission::deny();
        Permission::requireAny(['clients.manage_services', 'clients.assign']);
        csrf_check_or_die();
        ClientModel::removeEmployeeFromService($assignmentId);
        Flash::success('Assignment removed.');
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
        break;
    }

    case 'update_progress': {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        csrf_check_or_die();
        ClientModel::updateProgress($assignmentId, (int)$_POST['quantity_completed'], Auth::id());
        Flash::success('Progress updated.');
        redirect(url('clients', ['action' => 'view', 'id' => $clientId]));
        break;
    }

    default: {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'renewal' => $_GET['renewal'] ?? '',
            'service_id' => $_GET['service_id'] ?? '',
            'search' => trim($_GET['search'] ?? ''),
        ];
        $page = current_page_int();
        [$rows, $p] = ClientModel::paginate($page, 24, $filters);
        $allServices = ServiceModel::all();
        render_page('clients/list', compact('rows', 'p', 'filters', 'allServices'), 'Clients');
    }
}
