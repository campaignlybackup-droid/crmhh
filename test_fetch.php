<?php
require 'core/App.php';
require 'core/Database.php';
require 'core/Auth.php';
require 'core/Session.php';
Session::init();
$_SESSION['user_id'] = 1;
$_SESSION['role_slug'] = 'founder';

$_GET['page'] = 'leads';
$_GET['action'] = 'list';
ob_start();
require 'controllers/leads.php';
$html = ob_get_clean();

if (strpos($html, 'saveEdit(') !== false) {
    echo "Found saveEdit button!\n";
    // extract one button
    preg_match('/<button[^>]*saveEdit[^>]*>.*?<\/button>/s', $html, $matches);
    echo $matches[0] . "\n";
} else {
    echo "No saveEdit button found!\n";
}
