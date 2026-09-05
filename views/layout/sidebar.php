<?php
$page = $_GET['page'] ?? 'dashboard';
function nav_active(string $p, string $current): string { return $p === $current ? 'active' : ''; }
$canTeams = Permission::has('teams.manage') || !empty(UserModel::managedTeamsFor(Auth::id()));
?>
<nav class="sidebar" id="sidebar">
    <ul>
        <li class="<?= nav_active('dashboard', $page) ?>"><a href="<?= url('dashboard') ?>"><span class="nav-ico">&#9632;</span> Dashboard</a></li>
        <li class="<?= nav_active('leads', $page) ?>"><a href="<?= url('leads') ?>"><span class="nav-ico">&#9679;</span> Leads</a></li>
        <li class="<?= nav_active('clients', $page) ?>"><a href="<?= url('clients') ?>"><span class="nav-ico">&#9670;</span> Clients</a></li>
        <li class="<?= nav_active('tasks', $page) ?>"><a href="<?= url('tasks') ?>"><span class="nav-ico">&#9745;</span> Tasks</a></li>
        <li class="<?= nav_active('calendar', $page) ?>"><a href="<?= url('calendar') ?>"><span class="nav-ico">&#128197;</span> Calendar</a></li>
        <li class="<?= nav_active('availability', $page) ?>"><a href="<?= url('availability') ?>"><span class="nav-ico">&#9200;</span> Founder Availability</a></li>
        <li class="<?= nav_active('reports', $page) ?>"><a href="<?= url('reports') ?>"><span class="nav-ico">&#128221;</span> Daily Reports</a></li>
        <li class="<?= nav_active('leave', $page) ?>"><a href="<?= url('leave') ?>"><span class="nav-ico">&#128203;</span> Leave</a></li>
        <?php if ($canTeams): ?>
        <li class="<?= nav_active('teams', $page) ?>"><a href="<?= url('teams') ?>"><span class="nav-ico">&#128101;</span> Teams</a></li>
        <?php endif; ?>
        <?php if (Permission::has('users.manage')): ?>
        <li class="<?= nav_active('users', $page) ?>"><a href="<?= url('users') ?>"><span class="nav-ico">&#128100;</span> Users</a></li>
        <?php endif; ?>
        <?php if (Permission::has('roles.manage')): ?>
        <li class="<?= nav_active('roles', $page) ?>"><a href="<?= url('roles') ?>"><span class="nav-ico">&#128273;</span> Roles &amp; Permissions</a></li>
        <li class="<?= nav_active('services', $page) ?>"><a href="<?= url('services') ?>"><span class="nav-ico">&#128736;</span> Services</a></li>
        <?php endif; ?>
        <?php if (Permission::has('audit.view')): ?>
        <li class="<?= nav_active('audit', $page) ?>"><a href="<?= url('audit') ?>"><span class="nav-ico">&#128269;</span> Audit Log</a></li>
        <?php endif; ?>
    </ul>
</nav>
