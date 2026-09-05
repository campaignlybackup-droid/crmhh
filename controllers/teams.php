<?php

$canManage = Permission::has('teams.manage');
$managedTeamIds = array_column(UserModel::managedTeamsFor(Auth::id()), 'id');
if (!$canManage && empty($managedTeamIds)) {
    Permission::deny();
}

$action = $_GET['action'] ?? 'index';

function team_visible(int $teamId, bool $canManage, array $managedTeamIds): bool
{
    return $canManage || in_array($teamId, $managedTeamIds, true);
}

switch ($action) {
    case 'create': {
        Permission::require('teams.manage');
        $users = UserModel::activeSelectList();
        render_page('teams/form', ['team' => null, 'users' => $users, 'members' => [], 'managerIds' => []], 'New Team');
        break;
    }

    case 'store': {
        Permission::require('teams.manage');
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Team name');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('teams', ['action' => 'create'])); }
        $id = TeamModel::create(trim($_POST['name']), $_POST['description'] ?? '', $_POST['members'] ?? [], $_POST['managers'] ?? []);
        Flash::success('Team created.');
        redirect(url('teams', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        if (!team_visible($id, $canManage, $managedTeamIds)) Permission::deny();
        Permission::require('teams.manage');
        $team = TeamModel::find($id);
        if (!$team) fatal_error('Team not found.');
        $users = UserModel::activeSelectList();
        $members = array_column(TeamModel::members($id), 'id');
        $managerIds = array_column(TeamModel::managers($id), 'id');
        render_page('teams/form', compact('team', 'users', 'members', 'managerIds'), 'Edit Team');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        Permission::require('teams.manage');
        csrf_check_or_die();
        TeamModel::update($id, trim($_POST['name']), $_POST['description'] ?? '', $_POST['members'] ?? [], $_POST['managers'] ?? []);
        Flash::success('Team updated.');
        redirect(url('teams', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        Permission::require('teams.manage');
        csrf_check_or_die();
        TeamModel::softDelete($id);
        Flash::success('Team deleted.');
        redirect(url('teams'));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        if (!team_visible($id, $canManage, $managedTeamIds)) Permission::deny();
        $team = TeamModel::find($id);
        if (!$team) fatal_error('Team not found.');
        $members = TeamModel::members($id);
        $managers = TeamModel::managers($id);
        $workload = TeamModel::workload($id);
        render_page('teams/view', compact('team', 'members', 'managers', 'workload', 'canManage'), $team['name']);
        break;
    }

    default: {
        $teams = $canManage ? TeamModel::all() : Database::all(
            'SELECT * FROM teams WHERE deleted_at IS NULL AND id IN (' . implode(',', array_map('intval', $managedTeamIds ?: [0])) . ') ORDER BY name'
        );
        render_page('teams/list', compact('teams', 'canManage'), 'Teams');
    }
}
