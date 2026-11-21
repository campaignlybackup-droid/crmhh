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

if ($action === 'regenerate_api_key' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    if (!Auth::isFounder()) {
        Permission::deny();
    }
    $newKey = 'crm_founder_' . bin2hex(random_bytes(24));
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('founder_api_key', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$newKey]);
    Flash::success('Founder API Key regenerated successfully.');
    redirect(url('profile'));
}

$user = Auth::user();
$roles = Auth::roles();
$teams = UserModel::teamsFor($user['id']);

$founderApiKey = null;
if (Auth::isFounder()) {
    try {
        $founderApiKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'founder_api_key'");
        if (empty($founderApiKey)) {
            $founderApiKey = 'crm_founder_' . bin2hex(random_bytes(24));
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('founder_api_key', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$founderApiKey]);
        }
    } catch (Throwable $e) {}
}

render_page('profile/index', compact('user', 'roles', 'teams', 'founderApiKey'), 'My Profile');
