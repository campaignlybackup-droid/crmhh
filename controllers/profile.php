<?php

$action = $_GET['action'] ?? 'index';

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $user = Auth::user();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        Flash::error('Current password is incorrect.');
    } elseif (mb_strlen($new) < 8) {
        Flash::error('New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        Flash::error('New passwords do not match.');
    } else {
        UserModel::changeOwnPassword($user['id'], $new);
        Flash::success('Password changed successfully.');
        redirect(url('profile'));
    }
    redirect(url('profile'));
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $phone = trim($_POST['phone'] ?? '');
    Database::run('UPDATE users SET phone = ? WHERE id = ?', [$phone ?: null, Auth::id()]);
    Flash::success('Profile updated.');
    redirect(url('profile'));
}

$user = Auth::user();
$roles = Auth::roles();
$teams = UserModel::teamsFor($user['id']);

render_page('profile/index', compact('user', 'roles', 'teams'), 'My Profile');
