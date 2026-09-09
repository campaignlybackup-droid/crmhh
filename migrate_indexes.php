<?php
require __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasRole('founder')) Permission::deny();

$db = Database::pdo();
$queries = [];

$tables = ['leads', 'clients', 'tasks', 'client_services'];

foreach ($tables as $table) {
    $indexName = "idx_{$table}_deleted";
    $queries[] = "ALTER TABLE `$table` ADD INDEX `$indexName` (`deleted_at`)";
}

// Composite index for fast filtering
$queries[] = "ALTER TABLE leads ADD INDEX idx_leads_folder_deleted (folder_id, deleted_at)";
$queries[] = "ALTER TABLE tasks ADD INDEX idx_tasks_client_deleted (client_id, deleted_at)";

echo "<h1>Adding Database Indexes</h1>";

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "<p style='color:green'>Success: $q</p>";
    } catch (PDOException $e) {
        // Ignore duplicate key errors
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p style='color:gray'>Skipped (already exists): $q</p>";
        } else {
            echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

echo "<h2>Indexing complete! The database is now optimized for scale.</h2>";
echo "<a href='" . url('dashboard') . "'>Return to Dashboard</a>";
