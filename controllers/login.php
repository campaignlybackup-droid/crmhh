<?php
$action = $_GET['action'] ?? 'index';

if ($action === 'logout') {
    Auth::logout();
    redirect(url('login'));
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        Flash::error('Please enter both email and password.');
        redirect(url('login'));
    }

    [$ok, $error] = Auth::attempt($email, $password);
    if ($ok) {
        redirect(url('dashboard'));
    }
    Flash::error($error);
    $_SESSION['_old']['email'] = $email;
    redirect(url('login'));
}

$pageTitle = 'Login';
require __DIR__ . '/../views/layout/auth_header.php';
render('auth/login');
require __DIR__ . '/../views/layout/auth_footer.php';
unset($_SESSION['_old']);
