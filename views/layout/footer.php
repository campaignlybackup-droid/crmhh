</div><!-- /app-shell -->
<?php
$activeNavPage = $_GET['page'] ?? 'dashboard';
$mobileUnread = Auth::id() ? Notifier::unreadCount(Auth::id()) : 0;
?>
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <a href="<?= url('dashboard') ?>" class="mobile-nav-item <?= $activeNavPage === 'dashboard' ? 'active' : '' ?>">
        <span class="mobile-nav-icon">&#9632;</span>
        <span class="mobile-nav-label">Home</span>
    </a>
    <a href="<?= url('leads') ?>" class="mobile-nav-item <?= in_array($activeNavPage, ['leads', 'folder_settings']) ? 'active' : '' ?>">
        <span class="mobile-nav-icon">&#9679;</span>
        <span class="mobile-nav-label">Leads</span>
    </a>
    <a href="<?= url('tasks') ?>" class="mobile-nav-item <?= $activeNavPage === 'tasks' ? 'active' : '' ?>">
        <span class="mobile-nav-icon">&#9745;</span>
        <span class="mobile-nav-label">Tasks</span>
    </a>
    <a href="<?= url('clients') ?>" class="mobile-nav-item <?= $activeNavPage === 'clients' ? 'active' : '' ?>">
        <span class="mobile-nav-icon">&#9670;</span>
        <span class="mobile-nav-label">Clients</span>
    </a>
    <button type="button" class="mobile-nav-item" id="mobileNavMenuBtn" onclick="window.toggleSidebarMenu && window.toggleSidebarMenu()" aria-label="Open full menu">
        <span class="mobile-nav-icon">&#9776;<?php if ($mobileUnread): ?><span class="mobile-nav-badge" id="mobileMenuBadge"><?= $mobileUnread > 9 ? '9+' : $mobileUnread ?></span><?php endif; ?></span>
        <span class="mobile-nav-label">Menu</span>
    </button>
</nav>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>" defer></script>
</body>
</html>
