<?php

Auth::requireLogin();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'index': {
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 20;
        $filters = [
            'status' => $_GET['status'] ?? '',
            'severity' => $_GET['severity'] ?? '',
            'responsible_id' => $_GET['responsible_id'] ?? '',
            'client_id' => $_GET['client_id'] ?? '',
            'delayed_only' => !empty($_GET['delayed_only']),
            'search' => trim($_GET['search'] ?? '')
        ];

        [$rows, $p] = OperationsIssueModel::paginate($page, $perPage, $filters);
        $users = UserModel::activeSelectList();
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name ASC');
        $delayedCount = count(OperationsIssueModel::allDelayedOrAtRisk());

        render_page('operations_issues/index', compact('rows', 'p', 'filters', 'users', 'clients', 'delayedCount'), 'Operations Errors & Issue Log');
        break;
    }

    case 'create': {
        $users = UserModel::activeSelectList();
        $clients = Database::all('SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name ASC');
        $tasks = Database::all('SELECT id, title FROM tasks WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 50');
        render_page('operations_issues/form', compact('users', 'clients', 'tasks'), 'Log Operational Error');
        break;
    }

    case 'store': {
        csrf_check_or_die();
        $v = Validator::make($_POST)
            ->required('issue_title', 'Issue Title')
            ->required('responsible_id', 'Responsible Person');
        
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('operations_issues', ['action' => 'create']));
        }

        $id = OperationsIssueModel::create([
            'issue_title' => trim($_POST['issue_title']),
            'description' => trim($_POST['description'] ?? ''),
            'noticed_by_id' => Auth::id(),
            'responsible_id' => (int)$_POST['responsible_id'],
            'deduction_amount' => !empty($_POST['deduction_amount']) ? (float)$_POST['deduction_amount'] : 0.00,
            'deduction_type' => $_POST['deduction_type'] ?? 'deduction',
            'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
            'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
            'severity' => $_POST['severity'] ?? 'medium',
            'status' => 'assigned',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null
        ]);

        Flash::success('Operational error logged. Assigned to staff member and founder notified immediately.');
        redirect(url('operations_issues', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        $issue = OperationsIssueModel::find($id);
        if (!$issue) fatal_error('Operational error record not found.');

        $isFounder = Auth::hasRole('founder');
        $isManager = Auth::hasRole('manager');
        $isResponsible = (int)$issue['responsible_id'] === Auth::id();
        $isNoticed = (int)$issue['noticed_by_id'] === Auth::id();

        if (!$isFounder && !$isManager && !$isResponsible && !$isNoticed) {
            Permission::deny();
        }

        render_page('operations_issues/view', compact('issue', 'isFounder', 'isManager', 'isResponsible'), 'Issue Details: ' . $issue['issue_title']);
        break;
    }

    case 'rectify': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $issue = OperationsIssueModel::find($id);
        if (!$issue) fatal_error('Issue not found.');

        $notes = trim($_POST['correction_notes'] ?? '');
        if (!$notes) {
            Flash::error('Correction notes are required to mark issue rectified.');
            redirect(url('operations_issues', ['action' => 'view', 'id' => $id]));
        }

        OperationsIssueModel::markCorrected($id, Auth::id(), $notes);
        Flash::success('Issue marked as corrected and rectified. Founder and reporter have been notified.');
        redirect(url('operations_issues', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'escalate_founder': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $issue = OperationsIssueModel::find($id);
        if (!$issue) fatal_error('Issue not found.');

        Database::run("UPDATE operations_issues SET status = 'escalated_to_founder', is_delayed = 1, founder_escalated_at = NOW() WHERE id = ?", [$id]);
        
        $founders = Database::all('SELECT id FROM users WHERE is_founder = 1 AND status = "active"');
        foreach ($founders as $f) {
            Notifier::send(
                (int)$f['id'],
                'operations_manual_escalate',
                '🚨 URGENT FOUNDER ESCALATION: Operations Issue #' . $id,
                "Manual escalation by " . Auth::name() . ": '{$issue['issue_title']}'. Client or deliverable is at risk.",
                'operations_issue',
                $id
            );
        }

        Flash::success('Issue has been formally escalated to founder.');
        redirect(url('operations_issues', ['action' => 'view', 'id' => $id]));
        break;
    }

    default:
        fatal_error('Invalid action.');
}
