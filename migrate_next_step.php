<?php
require __DIR__ . '/core/bootstrap.php';
try {
    Database::run("ALTER TABLE leads ADD COLUMN next_step VARCHAR(255) DEFAULT NULL AFTER next_followup_date");
    echo "Success: next_step column added.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Success: next_step column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
