<?php
require __DIR__ . '/core/bootstrap.php';

$allowedPages = [
    'login', 'dashboard', 'users', 'teams', 'roles', 'leads', 'clients',
    'services', 'tasks', 'calendar', 'availability', 'reports', 'leave',
    'notifications', 'search', 'audit', 'profile', 'approvals',
];

$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    fatal_error('Page not found.');
}

$publicPages = ['login'];

if (!in_array($page, $publicPages, true)) {
    Auth::requireLogin();
} elseif (Auth::check() && Auth::user()) {
    redirect(url('dashboard'));
}

$controllerFile = __DIR__ . "/controllers/$page.php";
if (!file_exists($controllerFile)) {
    http_response_code(404);
    fatal_error('Page not found.');
}

require $controllerFile;
