<?php
require_once 'functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    markNotificationsRead($pdo, getCurrentUserId());
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit;
?>
