<?php
// Simple Database Setup Script
// WARNING: Delete this file from your server after running it!

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h2>CRM Database Setup</h2>";

function executeSqlFile($pdo, $filename) {
    if (!file_exists($filename)) {
        echo "<p style='color:red;'>Error: Cannot find $filename</p>";
        return false;
    }
    
    $sql = file_get_contents($filename);
    
    try {
        // We use execute instead of exec for multiple statements in some PDO setups, 
        // but for raw SQL dumps, sometimes we need to split it, or just use exec.
        // PDO::exec usually works for multiple queries if PDO::MYSQL_ATTR_MULTI_STATEMENTS is true.
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $pdo->exec($sql);
        echo "<p style='color:green;'>Success: Executed $filename</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Error executing $filename:<br>" . htmlspecialchars($e->getMessage()) . "</p>";
        return false;
    }
}

// Check connection
if (!$pdo) {
    die("Database connection failed. Check your config.php");
}

echo "<p>Connected to database successfully.</p>";

echo "<h3>Running schema.sql...</h3>";
executeSqlFile($pdo, 'schema.sql');

echo "<h3>Running migration_v2.sql...</h3>";
executeSqlFile($pdo, 'migration_v2.sql');

echo "<hr>";
echo "<h3 style='color:green;'>Setup Complete!</h3>";
echo "<p>You can now go to <a href='index.php'>the Login Page</a>.</p>";
echo "<p><strong>IMPORTANT:</strong> For security reasons, please delete this <code>setup_db.php</code> file from your server now.</p>";
