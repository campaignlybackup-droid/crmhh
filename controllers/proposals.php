<?php

Permission::requireAny(['proposals.view', 'proposals.manage']);

$action = $_GET['action'] ?? 'index';

function can_access_proposal(?array $proposal): bool {
    if (!$proposal) return false;
    $userId = Auth::id() ?: 1;
    if (Permission::isFounder($userId)) return true;
    if ((int)$proposal['assigned_user_id'] === $userId || (int)$proposal['created_by'] === $userId) return true;
    if (Permission::isManager($userId)) {
        $managedIds = Permission::managedUserIds($userId);
        if (in_array((int)$proposal['assigned_user_id'], $managedIds, true) || in_array((int)$proposal['created_by'], $managedIds, true)) {
            return true;
        }
    }
    return false;
}

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
        $userId = Auth::id() ?: 1;
        $users = UserModel::scopedSelectList($userId);
        $cVis = Permission::clientVisibility($userId);
        $clients = Database::all("SELECT id, name FROM clients WHERE deleted_at IS NULL AND ({$cVis['sql']}) ORDER BY name", $cVis['params']);
        $leadWhere = Permission::isFounder($userId) ? "" : " AND (assigned_user_id = $userId OR created_by = $userId)";
        $leads = Database::all("SELECT id, company FROM leads WHERE deleted_at IS NULL$leadWhere ORDER BY company");
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

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = ProposalModel::find($id);
        if (!$proposal) fatal_error('Proposal not found.');
        
        if (!can_access_proposal($proposal) || !Permission::has('proposals.manage')) {
            Permission::deny();
        }
        
        $userId = Auth::id() ?: 1;
        $cVis = Permission::clientVisibility($userId);
        $clients = Database::all("SELECT id, name FROM clients WHERE deleted_at IS NULL AND ({$cVis['sql']}) ORDER BY name", $cVis['params']);
        $leadWhere = Permission::isFounder($userId) ? "" : " AND (assigned_user_id = $userId OR created_by = $userId)";
        $leads = Database::all("SELECT id, company FROM leads WHERE deleted_at IS NULL$leadWhere ORDER BY company");
        $users = UserModel::scopedSelectList($userId);
        
        render_page('proposals/form', ['proposal' => $proposal, 'clients' => $clients, 'leads' => $leads, 'users' => $users], 'Edit Proposal');
        break;
    }

    case 'update': {
        Permission::require('proposals.manage');
        csrf_check_or_die();
        
        $id = (int)$_POST['id'];
        $proposal = ProposalModel::find($id);
        if (!$proposal || !can_access_proposal($proposal)) {
            Permission::deny();
        }

        $v = Validator::make($_POST)->required('title', 'Business Name / Title')->required('business_details', 'Business Details');
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('proposals', ['action' => 'edit', 'id' => $id]));
        }
        
        ProposalModel::update($id, $_POST);
        Flash::success('Proposal updated successfully.');
        redirect(url('proposals', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = ProposalModel::find($id);
        if (!$proposal || !can_access_proposal($proposal)) {
            Permission::deny();
        }
        
        $canManage = Permission::has('proposals.manage');
        $isAssigned = (int)$proposal['assigned_user_id'] === Auth::id();
        
        render_page('proposals/view', compact('proposal', 'canManage', 'isAssigned'), 'Proposal Details');
        break;
    }

    case 'update_status': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $proposal = ProposalModel::find($id);
        if (!$proposal || !can_access_proposal($proposal)) {
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
