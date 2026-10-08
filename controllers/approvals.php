<?php

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'index': {
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 20;
        [$rows, $p] = ApprovalModel::paginate($page, $perPage);
        $isReviewer = Auth::hasRole('founder') || Auth::hasRole('manager');
        render_page('approvals/index', compact('rows', 'p', 'isReviewer'), 'Approvals');
        break;
    }

    case 'create': {
        render_page('approvals/form', [], 'New Approval Request');
        break;
    }

    case 'store': {
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('title', 'Title');
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('approvals', ['action' => 'create']));
        }
        
        $id = ApprovalModel::create([
            'user_id' => Auth::id(),
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? ''
        ]);
        
        Flash::success('Approval request submitted successfully.');
        redirect(url('approvals', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        $approval = ApprovalModel::find($id);
        if (!$approval) fatal_error('Approval request not found.');
        
        $isFounder = Auth::hasRole('founder') || Auth::isFounder();
        $isManager = Auth::hasRole('manager') || !empty(UserModel::managedTeamsFor(Auth::id()));
        $isSender = (int)$approval['user_id'] === Auth::id();
        $isEditor = (int)$approval['editor_id'] === Auth::id() || (empty($approval['editor_id']) && $approval['stage'] === 'pending_editor');
        $managesSender = Permission::manages(Auth::id(), (int)$approval['user_id']);
        
        $canView = $isFounder || $isSender || $isEditor || ($isManager && $managesSender) || (int)$approval['reviewer_id'] === Auth::id();
        if (!$canView) {
            Permission::deny();
        }
        $isReviewer = $isFounder || ($isManager && $managesSender) || (int)$approval['reviewer_id'] === Auth::id();
        
        render_page('approvals/view', compact('approval', 'isReviewer', 'isSender'), 'Approval Details');
        break;
    }

    case 'editor_check': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $approval = ApprovalModel::find($id);
        if (!$approval) fatal_error('Approval request not found.');
        
        $decision = $_POST['decision'] ?? 'passed';
        $notes = trim($_POST['editor_notes'] ?? '');
        
        ApprovalModel::editorCheck($id, $decision === 'passed', Auth::id(), $notes);
        if ($decision === 'passed') {
            Flash::success('Approval checked by editor and sent to Manager Review.');
        } else {
            Flash::warning('Issues flagged and returned for rectification.');
        }
        redirect(url('approvals', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'review': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $approval = ApprovalModel::find($id);
        if (!$approval) fatal_error('Approval request not found.');
        
        $isFounder = Auth::hasRole('founder') || Auth::isFounder();
        $isManager = Auth::hasRole('manager') || !empty(UserModel::managedTeamsFor(Auth::id()));
        $managesSender = Permission::manages(Auth::id(), (int)$approval['user_id']);
        $canReview = $isFounder || ($isManager && $managesSender) || (int)$approval['reviewer_id'] === Auth::id();
        if (!$canReview) Permission::deny();
        
        $status = $_POST['status'] === 'approved' ? 'approved' : 'rejected';
        ApprovalModel::updateStatus($id, $status, Auth::id(), $_POST['reviewer_notes'] ?? null);
        
        Flash::success('Approval request has been ' . $status . '.');
        redirect(url('approvals', ['action' => 'view', 'id' => $id]));
        break;
    }

    default:
        fatal_error('Invalid action.');
}
