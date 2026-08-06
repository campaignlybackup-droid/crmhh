<?php
require_once 'config.php';
require_once __DIR__ . '/autoload.php';

// Force error mode to exception
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h1>Migration Phase 2 Output</h1>";

try {
    $sql = file_get_contents('migration_phase2.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already exists') !== false || $e->getCode() == '42S21' || $e->getCode() == '42S01') {
                    echo "<p style='color: orange;'>Ignored existing schema: " . htmlspecialchars($msg) . "</p>";
                } else {
                    echo "<p style='color: red;'>Failed statement: <code>" . htmlspecialchars($statement) . "</code><br>Error: " . htmlspecialchars($msg) . "</p>";
                }
            }
        }
    }
    
    echo "<h2 style='color: green;'>Migration Phase 2 Completed!</h2>";
    echo "<p>Please check the output above. Any orange warnings just mean the column/table already existed. Red errors are actual failures.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";
    
} catch (Throwable $e) {
    echo "<h2 style='color: red;'>Critical Failure!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
