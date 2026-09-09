<?php
require __DIR__ . '/core/bootstrap.php';
if (!Auth::hasRole('founder')) {
    die("Only founders can run migrations.\n");
}

echo "Running Proposals Migration...\n";

try {
    Database::run('ALTER TABLE proposals ADD COLUMN client_id INT UNSIGNED DEFAULT NULL AFTER status');
    Database::run('ALTER TABLE proposals ADD CONSTRAINT fk_proposals_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL');
    echo "Added client_id to proposals.\n";
} catch (Exception $e) {
    echo "client_id already exists or error: " . $e->getMessage() . "\n";
}

try {
    Database::run('ALTER TABLE proposals ADD COLUMN lead_id INT UNSIGNED DEFAULT NULL AFTER client_id');
    Database::run('ALTER TABLE proposals ADD CONSTRAINT fk_proposals_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL');
    echo "Added lead_id to proposals.\n";
} catch (Exception $e) {
    echo "lead_id already exists or error: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
