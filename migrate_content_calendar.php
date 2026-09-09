<?php
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasRole('founder')) {
    die('Only founder can run migrations.');
}

try {
    Database::run("
        CREATE TABLE IF NOT EXISTS service_subcategories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_svc_sub FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "service_subcategories table created.<br>";

    // Add tenure to client_services
    try {
        Database::run("ALTER TABLE client_services ADD COLUMN tenure ENUM('monthly', 'weekly', 'one_time') NOT NULL DEFAULT 'monthly' AFTER end_date");
        echo "tenure column added to client_services.<br>";
    } catch (Exception $e) {
        echo "tenure column already exists or error: " . $e->getMessage() . "<br>";
    }

    Database::run("
        CREATE TABLE IF NOT EXISTS client_service_quantities (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_service_id INT UNSIGNED NOT NULL,
            subcategory_id INT UNSIGNED NOT NULL,
            quantity_required INT UNSIGNED NOT NULL DEFAULT 0,
            quantity_completed INT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT fk_csq_cs FOREIGN KEY (client_service_id) REFERENCES client_services(id) ON DELETE CASCADE,
            CONSTRAINT fk_csq_sub FOREIGN KEY (subcategory_id) REFERENCES service_subcategories(id) ON DELETE CASCADE,
            UNIQUE KEY uk_csq (client_service_id, subcategory_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "client_service_quantities table created.<br>";

    Database::run("
        CREATE TABLE IF NOT EXISTS content_calendar (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            service_id INT UNSIGNED NOT NULL,
            subcategory_id INT UNSIGNED DEFAULT NULL,
            post_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT DEFAULT NULL,
            status ENUM('draft', 'pending_approval', 'scheduled', 'published') NOT NULL DEFAULT 'draft',
            assigned_to INT UNSIGNED DEFAULT NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_cc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_cc_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            CONSTRAINT fk_cc_sub FOREIGN KEY (subcategory_id) REFERENCES service_subcategories(id) ON DELETE SET NULL,
            CONSTRAINT fk_cc_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_cc_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "content_calendar table created.<br>";

    echo "Migration completed successfully!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
