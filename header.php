<?php
// Initialize notifications if logged in
$unread_count = 0;
$notifications = [];
if (isLoggedIn()) {
    $notifications = getUnreadNotifications($pdo, getCurrentUserId());
    $unread_count = count($notifications);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lightweight CRM</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-rocket-takeoff me-2"></i> CRM
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <?php if (isSuperAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="team_dashboard.php">Team Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="leads.php">Leads</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="clients.php">Clients</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">Projects</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tasks.php">Tasks</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="content_calendar.php">Content Calendar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="invoices.php">Invoices</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="proposals.php">Proposals</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Admin
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="users.php">Manage Users</a></li>
                        <li><a class="dropdown-item" href="lead_master.php">Lead Master Sheet</a></li>
                        <li><a class="dropdown-item" href="admin_daily_work.php">Team Daily Work</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="tasks.php">My Tasks</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="daily_work.php">Daily Work</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item dropdown me-3">
                    <a class="nav-link notification-bell" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notificationDropdown" style="width: 300px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header border-bottom py-2 d-flex justify-content-between align-items-center">
                            <strong>Notifications</strong>
                            <?php if ($unread_count > 0): ?>
                            <form action="mark_notifications.php" method="POST" class="m-0">
                                <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none">Mark all read</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php if (empty($notifications)): ?>
                            <li class="p-3 text-center text-muted small">No new notifications</li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <li class="p-2 border-bottom small">
                                    <?= h($notif['message']) ?>
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;"><?= h($notif['created_at']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2" style="width: 30px; height: 30px;">
                            <?= strtoupper(substr(getCurrentUsername(), 0, 1)) ?>
                        </div>
                        <?= h(getCurrentUsername()) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="main-content container-fluid">
    <!-- Messages / Alerts -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
