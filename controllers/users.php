<?php

Permission::require('users.manage');

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'create': {
        $roles = RoleModel::all();
        $managers = UserModel::activeSelectList();
        render_page('users/form', ['user' => null, 'roles' => $roles, 'managers' => $managers, 'userRoleIds' => []], 'New User');
        break;
    }

    case 'store': {
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Name')->required('email', 'Email')->email('email', 'Email')->required('password', 'Password');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('users', ['action' => 'create'])); }
        if (mb_strlen($_POST['password']) < 8) { Flash::error('Password must be at least 8 characters.'); redirect(url('users', ['action' => 'create'])); }
        if (UserModel::findByEmail($_POST['email'])) { Flash::error('A user with this email already exists.'); redirect(url('users', ['action' => 'create'])); }
        $id = UserModel::create($_POST, $_POST['roles'] ?? []);
        Flash::success('User created.');
        redirect(url('users', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        $user = UserModel::find($id);
        if (!$user) fatal_error('User not found.');
        $roles = UserModel::rolesFor($id);
        $teams = UserModel::teamsFor($id);
        render_page('users/view', compact('user', 'roles', 'teams'), $user['name']);
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        $user = UserModel::find($id);
        if (!$user) fatal_error('User not found.');
        $roles = RoleModel::all();
        $managers = array_filter(UserModel::activeSelectList(), fn($u) => $u['id'] != $id);
        $userRoleIds = array_column(UserModel::rolesFor($id), 'id');
        render_page('users/form', compact('user', 'roles', 'managers', 'userRoleIds'), 'Edit User');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Name')->required('email', 'Email')->email('email', 'Email');
        if ($v->fails()) { Flash::error($v->firstError()); redirect(url('users', ['action' => 'edit', 'id' => $id])); }
        $existing = UserModel::findByEmail($_POST['email']);
        if ($existing && (int)$existing['id'] !== $id) { Flash::error('Another user already uses this email.'); redirect(url('users', ['action' => 'edit', 'id' => $id])); }
        UserModel::update($id, $_POST, $_POST['roles'] ?? []);
        Flash::success('User updated.');
        redirect(url('users', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'disable': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        if ($id === Auth::id()) { Flash::error('You cannot disable your own account.'); redirect(url('users')); }
        UserModel::setStatus($id, 'disabled');
        Flash::success('User disabled.');
        redirect(url('users', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'enable': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        UserModel::setStatus($id, 'active');
        Flash::success('User enabled.');
        redirect(url('users', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'reset_password': {
        $id = (int)($_POST['id'] ?? 0);
        csrf_check_or_die();
        $newPassword = bin2hex(random_bytes(5));
        UserModel::resetPassword($id, $newPassword);
        Flash::success("Password reset. Temporary password: $newPassword (share this with the user securely; they will be asked to change it on next login).");
        redirect(url('users', ['action' => 'view', 'id' => $id]));
        break;
    }

    default: {
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $page = current_page_int();
        [$rows, $p] = UserModel::paginate($page, 25, $search, $status);
        render_page('users/list', compact('rows', 'p', 'search', 'status'), 'Users');
    }
}
