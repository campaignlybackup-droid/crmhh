<?php
require 'core/init.php';

Database::run('CREATE TABLE IF NOT EXISTS lead_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lead_folders_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

Database::run('CREATE TABLE IF NOT EXISTS lead_folder_users (
    folder_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (folder_id, user_id),
    CONSTRAINT fk_lfu_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_lfu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

try {
    Database::run('ALTER TABLE leads ADD COLUMN folder_id INT UNSIGNED DEFAULT NULL');
    Database::run('ALTER TABLE leads ADD CONSTRAINT fk_leads_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE SET NULL');
} catch (Exception $e) {
    echo "Column folder_id might already exist.\n";
}

// Revoke assign permission from managers
$assignPermId = Database::scalar("SELECT id FROM permissions WHERE slug = 'leads.assign'");
$managerRoleId = Database::scalar("SELECT id FROM roles WHERE slug = 'manager'");
if ($assignPermId && $managerRoleId) {
    Database::run("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?", [$managerRoleId, $assignPermId]);
}
echo "Migration complete.\n";
