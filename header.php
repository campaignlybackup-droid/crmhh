<?php
require_once __DIR__ . '/includes/auth.php';

// Initialize notifications if logged in
$unread_count = 0;
$notifications = [];
if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    // Assuming getUnreadNotifications exists in a notifications helper, but we might just inline it or create the function here:
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();
    } catch (\Throwable $e) { $notifications = []; }
    $unread_count = count($notifications);
}
$username = getCurrentUsername();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$userRoles = isLoggedIn() ? getUserRoles($pdo, getCurrentUserId()) : [];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Operating System</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>" rel="stylesheet">
    
    <script>
        // Set theme before render to prevent flash
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>
</head>
<body>

<?php if (isLoggedIn()): ?>
<div class="layout-wrapper">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="brand">
            <i class="bi bi-hexagon-fill me-2"></i> CRM OS
        </a>
        <div class="sidebar-nav">
            
            <div class="sidebar-nav-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="team_dashboard.php" class="sidebar-link <?= $current_page == 'team_dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i> Team Performance
            </a>
            <a href="calendar.php" class="sidebar-link <?= $current_page == 'calendar.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Calendar
            </a>
            
            <a href="projects.php" class="sidebar-link <?= $current_page == 'projects.php' ? 'active' : '' ?>">
                <i class="bi bi-briefcase-fill"></i> Projects
            </a>
            <a href="tasks.php" class="sidebar-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> Tasks
            </a>
            
            <?php if ($isManager): ?>
            <a href="clients.php" class="sidebar-link <?= $current_page == 'clients.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Clients
            </a>
            <a href="leads.php" class="sidebar-link <?= $current_page == 'leads.php' ? 'active' : '' ?>">
                <i class="bi bi-funnel-fill"></i> Leads
            </a>
            <?php endif; ?>
            
            <?php if ($isFounder): ?>
            <a href="reports.php" class="sidebar-link <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line-fill"></i> Reports
            </a>
            <?php endif; ?>
            <a href="activity.php" class="sidebar-link <?= $current_page == 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i> Activity Log
            </a>
            <a href="chat.php" class="sidebar-link <?= $current_page == 'chat.php' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots-fill"></i> Team Chat
            </a>
            
            <div class="sidebar-nav-title">Company</div>
            <a href="content_calendar.php" class="sidebar-link <?= $current_page == 'content_calendar.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Content Calendar
            </a>
            <a href="hr.php" class="sidebar-link <?= $current_page == 'hr.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i> HR & Leaves
            </a>
            
            <?php if ($isFounder): ?>
            <a href="invoices.php" class="sidebar-link <?= $current_page == 'invoices.php' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Invoices
            </a>
            <?php endif; ?>
            
            <?php if ($isFounder): ?>
            <div class="sidebar-heading mt-4 text-uppercase fw-bold text-muted" style="font-size: 0.75rem; padding: 0 1rem;">System Settings</div>
            <a href="users.php" class="sidebar-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> Users
            </a>
            <a href="export_all_data.php" class="sidebar-link <?= $current_page == 'export_all_data.php' ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-up-right"></i> Data Export & Backup
            </a>
            <?php endif; ?>
            <a href="tasks.php" class="sidebar-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> My Tasks
            </a>
            <a href="daily_work.php" class="sidebar-link <?= $current_page == 'daily_work.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-check"></i> Daily Work
            </a>
            
            
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link text-body d-lg-none me-2 p-0" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <div class="search-bar d-none d-md-block">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" placeholder="Search projects, tasks, clients...">
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Theme Toggle -->
                <button class="btn btn-link text-body p-0" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="bi bi-moon-fill fs-5" id="themeIcon"></i>
                </button>

                <!-- Notifications -->
                <div class="dropdown">
                    <a class="notification-bell text-decoration-none" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Notifications</h6>
                            <?php if ($unread_count > 0): ?>
                            <form action="mark_notifications.php" method="POST" class="m-0">
                                <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none">Mark all read</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php if (empty($notifications)): ?>
                            <li class="p-4 text-center text-muted small">
                                <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                                You're all caught up!
                            </li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <li class="p-3 border-bottom small position-relative">
                                    <div class="d-flex gap-2">
                                        <div class="text-primary mt-1"><i class="bi bi-info-circle-fill"></i></div>
                                        <div>
                                            <?= h($notif['message']) ?>
                                            <div class="text-muted mt-1" style="font-size: 0.7rem;"><?= h($notif['created_at']) ?></div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Profile -->
                <div class="dropdown">
                    <a class="d-flex align-items-center text-decoration-none text-body" href="#" data-bs-toggle="dropdown">
                        <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width: 35px; height: 35px; font-weight: 600;">
                            <?= strtoupper(substr(getCurrentUsername(), 0, 1)) ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end mt-2">
                        <li class="px-3 py-2 border-bottom mb-1">
                            <div class="fw-bold"><?= h(getCurrentUsername()) ?></div>
                            <div class="small text-muted text-capitalize"><?= h(implode(', ', $userRoles)) ?></div>
                        </li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="main-content">
            <!-- Alerts -->
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= h($_SESSION['flash_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($_SESSION['flash_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

<?php else: ?>
<!-- For unauthenticated pages like login -->
<div class="main-content p-0" style="height: 100vh;">
<?php endif; ?>
