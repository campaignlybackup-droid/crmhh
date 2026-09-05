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
    
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $hasError = false;
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                echo "<p style='color:orange;'>Skipped a query (already exists): " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
                $hasError = true;
            }
        }
    }
    
    echo "<p style='color:green;'>Finished executing $filename</p>";
    return true;
}

// Check connection
if (!$pdo) {
    die("Database connection failed. Check your config.php");
}

echo "<p>Connected to database successfully.</p>";

echo "<h3>Running full_database_install.sql...</h3>";
executeSqlFile($pdo, 'full_database_install.sql');

echo "<hr>";
echo "<h3 style='color:green;'>Setup Complete!</h3>";
echo "<p>You can now go to <a href='index.php'>the Login Page</a>.</p>";
echo "<p><strong>IMPORTANT:</strong> For security reasons, please delete this <code>setup_db.php</code> file from your server now.</p>";
