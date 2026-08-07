<?php
require_once 'config.php';
try {
    $pdo->query("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'manager', 'user') DEFAULT 'user'");
    echo "Successfully updated role ENUM.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
