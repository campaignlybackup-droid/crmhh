<?php
require __DIR__ . '/core/bootstrap.php';

echo "Starting Announcements & Timezone migration...\n";

Database::beginTransaction();

try {
    // 1. Create Announcements table
    Database::run("
        CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "- Created announcements table\n";

    // 2. Add permission
    $perm = Database::one("SELECT id FROM permissions WHERE slug = 'announcements.manage'");
    if (!$perm) {
        Database::run("INSERT INTO permissions (slug, name, `group`) VALUES ('announcements.manage', 'Manage announcements', 'admin')");
        $permId = Database::lastInsertId();
        
        $founder = Database::one("SELECT id FROM roles WHERE slug = 'founder'");
        if ($founder) {
            Database::run("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$founder['id'], $permId]);
        }
        echo "- Added announcements.manage permission\n";
    }

    // 3. Update Timezone in app_settings
    Database::run("UPDATE app_settings SET value = 'Asia/Dubai' WHERE `key` = 'timezone'");
    echo "- Updated app_settings timezone to Asia/Dubai\n";

    Database::commit();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    Database::rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
