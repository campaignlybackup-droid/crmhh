<?php
require __DIR__ . '/core/bootstrap.php';

echo "Starting Announcements & Timezone migration...\n";

$db = Database::getInstance();
$db->beginTransaction();

try {
    // 1. Create Announcements table
    $db->exec("
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
    $stmt = $db->prepare("SELECT id FROM permissions WHERE slug = 'announcements.manage'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO permissions (slug, name, `group`) VALUES ('announcements.manage', 'Manage announcements', 'admin')")->execute();
        $permId = $db->lastInsertId();
        
        $stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'founder'");
        $stmt->execute();
        $founder = $stmt->fetch();
        
        if ($founder) {
            $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$founder['id'], $permId]);
        }
        echo "- Added announcements.manage permission\n";
    }

    // 3. Update Timezone in app_settings
    $db->prepare("UPDATE app_settings SET value = 'Asia/Dubai' WHERE `key` = 'timezone'")->execute();
    echo "- Updated app_settings timezone to Asia/Dubai\n";

    $db->commit();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
