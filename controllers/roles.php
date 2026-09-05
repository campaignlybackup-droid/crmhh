<?php

Permission::require('roles.manage');

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'create': {
        $permissions = RoleModel::allPermissions();
        render_page('roles/form', ['role' => null, 'permissions' => $permissions, 'rolePermIds' => []], 'New Role');
        break;
    }

    case 'store': {
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Role name');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('roles', ['action' => 'create'])); }
        $slug = RoleModel::slugify($_POST['name']);
        RoleModel::create(trim($_POST['name']), $slug, $_POST['description'] ?? '', $_POST['permissions'] ?? []);
        Flash::success('Role created.');
        redirect(url('roles'));
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        $role = RoleModel::find($id);
        if (!$role) fatal_error('Role not found.');
        $permissions = RoleModel::allPermissions();
        $rolePermIds = array_column(RoleModel::permissionsFor($id), 'id');
        render_page('roles/form', compact('role', 'permissions', 'rolePermIds'), 'Edit Role');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        RoleModel::update($id, trim($_POST['name']), $_POST['description'] ?? '', $_POST['permissions'] ?? []);
        Flash::success('Role updated.');
        redirect(url('roles'));
        break;
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        if (!RoleModel::delete($id)) {
            Flash::error('System roles (Founder, Manager) cannot be deleted.');
        } else {
            Flash::success('Role deleted.');
        }
        redirect(url('roles'));
        break;
    }

    default: {
        $roles = RoleModel::all();
        foreach ($roles as &$r) {
            $r['permission_count'] = count(RoleModel::permissionsFor($r['id']));
            $r['user_count'] = (int)Database::scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = ?', [$r['id']]);
        }
        render_page('roles/index', compact('roles'), 'Roles & Permissions');
    }
}
