<?php

Permission::requireAny(['proposals.view', 'proposals.manage']);

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'index': {
        $page = current_page_int();
        $filters = ['status' => $_GET['status'] ?? ''];
        [$rows, $p] = ProposalModel::paginate($page, 20, $filters);
        
        $canManage = Permission::has('proposals.manage');
        render_page('proposals/index', compact('rows', 'p', 'filters', 'canManage'), 'Proposals');
        break;
    }

    case 'create': {
        Permission::require('proposals.manage');
        $users = UserModel::activeSelectList();
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name');
        $leads = Database::all('SELECT id, company_name FROM leads WHERE deleted_at IS NULL ORDER BY company_name');
        $preselectClientId = (int)($_GET['client_id'] ?? 0);
        $preselectLeadId = (int)($_GET['lead_id'] ?? 0);
        render_page('proposals/form', compact('users', 'clients', 'leads', 'preselectClientId', 'preselectLeadId'), 'New Proposal Request');
        break;
    }

    case 'store': {
        Permission::require('proposals.manage');
        csrf_check_or_die();
        
        $v = Validator::make($_POST)->required('title', 'Business Name / Title')->required('business_details', 'Business Details');
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('proposals', ['action' => 'create']));
        }
        
        $id = ProposalModel::create($_POST);
        Flash::success('Proposal request created successfully.');
        redirect(url('proposals', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = ProposalModel::find($id);
        if (!$proposal) fatal_error('Proposal not found.');
        
        $canManage = Permission::has('proposals.manage');
        $isAssigned = (int)$proposal['assigned_user_id'] === Auth::id();
        
        if (!$canManage && !$isAssigned) {
            Permission::deny();
        }
        
        render_page('proposals/view', compact('proposal', 'canManage', 'isAssigned'), 'Proposal Details');
        break;
    }

    case 'update_status': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $proposal = ProposalModel::find($id);
        if (!$proposal) fatal_error('Proposal not found.');
        
        $canManage = Permission::has('proposals.manage');
        $isAssigned = (int)$proposal['assigned_user_id'] === Auth::id();
        
        if (!$canManage && !$isAssigned) {
            Permission::deny();
        }
        
        $status = in_array($_POST['status'], ['pending', 'in_progress', 'ready', 'sent']) ? $_POST['status'] : 'pending';
        ProposalModel::updateStatus($id, $status, $_POST['notes'] ?? $proposal['notes']);
        
        Flash::success('Proposal status updated.');
        redirect(url('proposals', ['action' => 'view', 'id' => $id]));
        break;
    }

    default:
        fatal_error('Invalid action.');
}
