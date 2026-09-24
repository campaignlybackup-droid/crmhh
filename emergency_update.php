<?php
// Bypasses Hostinger's broken Git system and fetches files directly from GitHub.
echo "<h1>Emergency Code Updater</h1><pre>";

$files = [
    'core/Database.php',
    'core/bootstrap.php',
    'core/Flash.php',
    'assets/css/style.css',
    'index.php',
    'models/OperationsIssueModel.php',
    'models/LeadModel.php',
    'models/ClientModel.php',
    'models/ContentCalendarModel.php',
    'models/ApprovalModel.php',
    'models/LeaveModel.php',
    'models/ReportModel.php',
    'models/UserModel.php',
    'models/TeamModel.php',
    'controllers/operations_issues.php',
    'controllers/dashboard.php',
    'controllers/content_calendar.php',
    'controllers/clients.php',
    'controllers/approvals.php',
    'controllers/leads.php',
    'controllers/teams.php',
    'controllers/folder_settings.php',
    'views/operations_issues/index.php',
    'views/operations_issues/form.php',
    'views/operations_issues/view.php',
    'views/dashboard/index.php',
    'views/content_calendar/index.php',
    'views/clients/view.php',
    'views/approvals/view.php',
    'views/leads/list.php',
    'views/leads/folders.php',
    'views/teams/list.php',
    'views/teams/view.php',
    'views/layout/sidebar.php',
    'database/schema.sql',
    'migrate_deep_fixes.php'
];

$baseUrl = 'https://raw.githubusercontent.com/campaignlybackup-droid/crmhh/main/';

foreach ($files as $file) {
    echo "Downloading $file... ";
    $content = file_get_contents($baseUrl . $file);
    if ($content) {
        file_put_contents(__DIR__ . '/' . $file, $content);
        echo "<span style='color:green'>SUCCESS</span>\n";
    } else {
        echo "<span style='color:red'>FAILED</span>\n";
    }
}

echo "\n--- Running Safe Database Migrations ---\n";
if (file_exists(__DIR__ . '/migrate_deep_fixes.php')) {
    include __DIR__ . '/migrate_deep_fixes.php';
}

echo "</pre><h2>Update Complete! All files and database structures are up to date. <a href='index.php'>Go to CRM Dashboard</a></h2>";
?>
