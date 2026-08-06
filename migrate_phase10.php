<?php
require_once 'config.php';

echo "<h1>Migration Phase 10 Output</h1>";

try {
    $sql = file_get_contents('migration_phase10.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: green;'>Executed: " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<p style='color: orange;'>Skipped (Already exists): " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
            } else {
                throw $e;
            }
        }
    }

    echo "<h2 style='color: green;'>Migration Phase 10 Completed Successfully!</h2>";
    echo "<p>Invoices now have a payment_date column for accurate revenue tracking.</p>";
    echo "<a href='dashboard.php'>Go to Dashboard</a>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Critical Failure!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
