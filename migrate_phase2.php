<?php
require_once 'config.php';
require_once __DIR__ . '/autoload.php';

try {
    $sql = file_get_contents('migration_phase2.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "<h1>Migration Phase 2 Completed Successfully!</h1>";
    echo "<p>All tables and columns have been created/updated without data loss.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";
    
} catch (PDOException $e) {
    echo "<h1>Migration Failed!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
