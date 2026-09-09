<?php
$page = $_GET['page'] ?? 'dashboard';
function nav_active(string $p, string $current): string { return $p === $current ? 'active' : ''; }
$canTeams = Permission::has('teams.manage') || !empty(UserModel::managedTeamsFor(Auth::id()));
?>
<nav class="sidebar" id="sidebar">
    <ul>
        <li class="<?= nav_active('dashboard', $page) ?>"><a href="<?= url('dashboard') ?>"><span class="nav-ico">&#9632;</span> Dashboard</a></li>
        <li class="<?= nav_active('announcements', $page) ?>"><a href="<?= url('announcements') ?>"><span class="nav-ico">&#128227;</span> Announcements</a></li>
        <li class="<?= nav_active('leads', $page) ?>"><a href="<?= url('leads') ?>"><span class="nav-ico">&#9679;</span> Leads</a></li>
        <li class="<?= nav_active('clients', $page) ?>"><a href="<?= url('clients') ?>"><span class="nav-ico">&#9670;</span> Clients</a></li>
        <li class="<?= nav_active('tasks', $page) ?>"><a href="<?= url('tasks') ?>"><span class="nav-ico">&#9745;</span> Tasks</a></li>
        <li class="<?= nav_active('calendar', $page) ?>"><a href="<?= url('calendar') ?>"><span class="nav-ico">&#128197;</span> Calendar</a></li>
        <li class="<?= nav_active('availability', $page) ?>"><a href="<?= url('availability') ?>"><span class="nav-ico">&#9200;</span> Founder Availability</a></li>
        <li class="<?= nav_active('approvals', $page) ?>"><a href="<?= url('approvals') ?>"><span class="nav-ico">&#10004;</span> Approvals</a></li>
        <?php if (Permission::has('proposals.view')): ?>
        <li class="<?= nav_active('proposals', $page) ?>"><a href="<?= url('proposals') ?>"><span class="nav-ico">&#128221;</span> Proposals</a></li>
        <?php endif; ?>
        <li class="<?= nav_active('reports', $page) ?>"><a href="<?= url('reports') ?>"><span class="nav-ico">&#128202;</span> Reports</a></li>
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
        <li class="<?= nav_active('tasks', $page) ?>">
            <a href="<?= url('tasks') ?>"><i class="icon" style="background-image:url('data:image/svg+xml;utf8,<svg viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22white%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><path d=%22M9 11l3 3L22 4%22/><path d=%22M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11%22/></svg>')"></i> My Tasks</a>
        </li>
        <li class="<?= nav_active('content_calendar', $page) ?>">
            <a href="<?= url('content_calendar') ?>"><i class="icon" style="background-image:url('data:image/svg+xml;utf8,<svg viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22white%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><rect x=%223%22 y=%224%22 width=%2218%22 height=%2218%22 rx=%222%22 ry=%222%22/><line x1=%2216%22 y1=%222%22 x2=%2216%22 y2=%226%22/><line x1=%228%22 y1=%222%22 x2=%228%22 y2=%226%22/><line x1=%223%22 y1=%2210%22 x2=%2221%22 y2=%2210%22/></svg>')"></i> Content Calendar</a>
        </li>
        <?php if (Permission::has('calendar.view')): ?>
        <li class="<?= nav_active('audit', $page) ?>"><a href="<?= url('audit') ?>"><span class="nav-ico">&#128269;</span> Audit Log</a></li>
        <?php endif; ?>
        <?php if (Auth::hasRole('founder')): ?>
        <li class="<?= nav_active('backup', $page) ?>"><a href="<?= url('backup') ?>"><span class="nav-ico">&#128190;</span> Full Backup</a></li>
        <?php endif; ?>
    </ul>
</nav>
