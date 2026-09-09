<?php
require 'core/init.php';

echo "Starting Advanced Folders Migration...\n";

// 1. Update lead_statuses
try {
    Database::run('ALTER TABLE lead_statuses ADD COLUMN folder_id INT UNSIGNED DEFAULT NULL');
    Database::run('ALTER TABLE lead_statuses ADD CONSTRAINT fk_ls_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE');
    echo "Added folder_id to lead_statuses.\n";
} catch (Exception $e) {
    echo "Column folder_id on lead_statuses might already exist.\n";
}

try {
    Database::run('ALTER TABLE lead_statuses DROP INDEX slug');
    echo "Dropped unique constraint on slug in lead_statuses.\n";
} catch (Exception $e) {
    echo "Index slug might already be dropped.\n";
}

// 2. Create custom fields tables
Database::run('CREATE TABLE IF NOT EXISTS folder_custom_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM("text", "number", "select", "date") NOT NULL DEFAULT "text",
    options JSON DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fcf_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Created folder_custom_fields table.\n";

Database::run('CREATE TABLE IF NOT EXISTS lead_custom_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_value TEXT,
    CONSTRAINT fk_lcv_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_lcv_field FOREIGN KEY (field_id) REFERENCES folder_custom_fields(id) ON DELETE CASCADE,
    UNIQUE KEY idx_lead_field (lead_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Created lead_custom_values table.\n";

// 3. Seed default statuses into existing custom folders
$folders = Database::all('SELECT id FROM lead_folders');
$defaultStatuses = Database::all('SELECT * FROM lead_statuses WHERE folder_id IS NULL');

foreach ($folders as $folder) {
    foreach ($defaultStatuses as $ds) {
        $exists = Database::scalar('SELECT 1 FROM lead_statuses WHERE folder_id = ? AND slug = ?', [$folder['id'], $ds['slug']]);
        if (!$exists) {
            Database::run(
                'INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$ds['name'], $ds['slug'], $ds['color'], $ds['sort_order'], $ds['is_won'], $ds['is_lost'], $ds['is_default'], $folder['id']]
            );
        }
    }
}
echo "Seeded default statuses into existing folders.\n";

echo "Migration complete.\n";
