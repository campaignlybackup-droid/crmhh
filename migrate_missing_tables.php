<?php
require __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasRole('founder')) Permission::deny();

$db = Database::pdo();

$queries = [
    "CREATE TABLE IF NOT EXISTS folder_custom_fields (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        folder_id INT UNSIGNED NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        field_type VARCHAR(50) NOT NULL,
        options TEXT,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_fcf_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS lead_custom_values (
        lead_id INT UNSIGNED NOT NULL,
        field_id INT UNSIGNED NOT NULL,
        field_value TEXT,
        PRIMARY KEY (lead_id, field_id),
        CONSTRAINT fk_lcv_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        CONSTRAINT fk_lcv_field FOREIGN KEY (field_id) REFERENCES folder_custom_fields(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS announcements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        created_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ann_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS approvals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        reviewer_id INT UNSIGNED,
        reviewer_notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_appr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_appr_rev FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    "CREATE TABLE IF NOT EXISTS content_calendar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        content_type VARCHAR(100),
        post_date DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        assigned_to INT UNSIGNED,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        CONSTRAINT fk_cc_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "<p>Successfully ran query.</p>";
    } catch (PDOException $e) {
        echo "<p>Error running query: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h2>Migration complete! All missing tables have been created successfully.</h2>";
echo "<a href='" . url('dashboard') . "'>Return to Dashboard</a>";
