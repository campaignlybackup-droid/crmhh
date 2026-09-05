<?php $appName = e(config('app')['name'] ?? 'Agency CRM'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ? e($pageTitle) . ' · ' : '' ?><?= $appName ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">&#9776;</button>
    <a href="<?= url('dashboard') ?>" class="brand"><?= $appName ?></a>
    <form class="global-search" method="get" action="<?= url('search') ?>">
        <input type="hidden" name="page" value="search">
        <input type="text" name="q" placeholder="Search leads, clients, tasks, people&hellip;" value="<?= old('q') ?>">
    </form>
    <div class="topbar-right">
        <div class="dropdown" id="notifDropdown">
            <button class="icon-btn" id="notifBtn">&#128276;<?php $uc = Notifier::unreadCount(Auth::id()); if ($uc): ?><span class="badge-dot"><?= $uc > 9 ? '9+' : $uc ?></span><?php endif; ?></button>
            <div class="dropdown-menu" id="notifMenu">
                <div class="dropdown-header">Notifications</div>
                <?php $notifs = Notifier::recent(Auth::id(), 8); if (empty($notifs)): ?>
                    <div class="dropdown-empty">No notifications yet.</div>
                <?php else: foreach ($notifs as $n): ?>
                    <div class="dropdown-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="notif-title"><?= e($n['title']) ?></div>
                        <?php if ($n['message']): ?><div class="notif-msg"><?= e($n['message']) ?></div><?php endif; ?>
                        <div class="notif-time"><?= time_ago($n['created_at']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
                <a href="<?= url('notifications') ?>" class="dropdown-footer">View all</a>
            </div>
        </div>
        <div class="dropdown" id="userDropdown">
            <button class="user-btn" id="userBtn"><?= e($currentUser['name']) ?> <span class="caret">&#9662;</span></button>
            <div class="dropdown-menu" id="userMenu">
                <a href="<?= url('profile') ?>" class="dropdown-item-link">My Profile</a>
                <a href="<?= url('login', ['action'=>'logout']) ?>" class="dropdown-item-link">Logout</a>
            </div>
        </div>
    </div>
</header>
<div class="app-shell">
