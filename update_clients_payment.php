<?php
require_once 'config.php';
try {
    $pdo->query("ALTER TABLE clients ADD COLUMN monthly_payment_date VARCHAR(50) DEFAULT NULL");
    echo "Added monthly_payment_date\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
