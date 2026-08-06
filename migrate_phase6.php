<?php
require_once 'config.php';
require_once __DIR__ . '/autoload.php';

echo "<h1>Migration Phase 6 Output</h1>";

try {
    // 1. Run SQL (Split by statement to handle duplicates)
    $sql = file_get_contents('migration_phase6.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: green;'>Executed: " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
        } catch (PDOException $e) {
            // Ignore duplicate column/key errors
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<p style='color: orange;'>Skipped (Already exists): " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
            } else {
                throw $e;
            }
        }
    }

    echo "<h2 style='color: green;'>Migration Phase 6 Completed Successfully!</h2>";
    echo "<p>Workflow engine tables have been created.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Critical Failure!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
