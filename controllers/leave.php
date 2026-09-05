<?php

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'apply': {
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('start_date', 'Start date')->required('end_date', 'End date')->required('reason', 'Reason')->date('start_date', 'Start date')->date('end_date', 'End date');
        if ($v->fails()) {
            Flash::error($v->firstError());
        } elseif (strtotime($_POST['end_date']) < strtotime($_POST['start_date'])) {
            Flash::error('End date cannot be before the start date.');
        } else {
            LeaveModel::apply(Auth::id(), $_POST['start_date'], $_POST['end_date'], trim($_POST['reason']));
            Flash::success('Leave request submitted.');
        }
        redirect(url('leave'));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        if (!LeaveModel::canAccess($id)) Permission::deny();
        $leave = LeaveModel::find($id);
        if (!$leave) fatal_error('Leave request not found.');
        render_page('leave/view', compact('leave'), 'Leave Request');
        break;
    }

    case 'decide': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeaveModel::canAccess($id)) Permission::deny();
        Permission::requireAny(['leave.approve_team', 'leave.approve_all']);
        csrf_check_or_die();
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['approved', 'rejected'], true)) {
            LeaveModel::decide($id, $status, $_POST['decision_note'] ?? null);
            Flash::success('Leave request ' . $status . '.');
        }
        redirect(url('leave', ['action' => 'view', 'id' => $id]));
        break;
    }

    default: {
        $filters = ['status' => $_GET['status'] ?? ''];
        $page = current_page_int();
        [$rows, $p] = LeaveModel::paginate($page, 20, $filters);
        render_page('leave/index', compact('rows', 'p', 'filters'), 'Leave Management');
    }
}
