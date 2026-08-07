<?php
require_once 'config.php';

$queries = [
    // 1. Remove monthly_rate from users and update role enum
    "ALTER TABLE users DROP COLUMN monthly_rate",
    "ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'manager', 'user') DEFAULT 'user'",
    
    // 2. Add assigned_to to leads
    "ALTER TABLE leads ADD COLUMN assigned_to INT, ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL",
    
    // 3. Add assigned_to to clients
    "ALTER TABLE clients ADD COLUMN assigned_to INT, ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL",
    
    // 4. Update projects: drop assigned_team text, add assigned_to INT
    "ALTER TABLE projects DROP COLUMN assigned_team",
    "ALTER TABLE projects ADD COLUMN assigned_to INT, ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL",
    
    // 5. Add assigned_to to invoices
    "ALTER TABLE invoices ADD COLUMN assigned_to INT, ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL",
    
    // 6. Create content_calendar table if not exists
    "CREATE TABLE IF NOT EXISTS content_calendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_title VARCHAR(255) NOT NULL,
        platform ENUM('IG', 'TikTok', 'LinkedIn') DEFAULT 'IG',
        status ENUM('Draft', 'Scheduled', 'Posted') DEFAULT 'Draft',
        post_date DATE,
        assigned_to INT,
        caption TEXT,
        drive_link VARCHAR(255),
        client_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    )",
    
    // 7. Create daily_work table
    "CREATE TABLE IF NOT EXISTS daily_work (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        work_date DATE NOT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // 8. Create proposals table
    "CREATE TABLE IF NOT EXISTS proposals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proposal_name VARCHAR(255) NOT NULL,
        client_id INT,
        amount DECIMAL(10, 2) DEFAULT 0.00,
        status ENUM('Draft', 'Sent', 'Accepted', 'Rejected') DEFAULT 'Draft',
        drive_link VARCHAR(255),
        assigned_to INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )",

    // 9. Modify projects table for video metrics
    "ALTER TABLE projects MODIFY COLUMN shoot_date VARCHAR(255)",
    "ALTER TABLE projects ADD COLUMN total_videos_planned INT DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN videos_shot INT DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN videos_edited INT DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN videos_uploaded INT DEFAULT 0",

    // 10. Update users table for proper team management
    "ALTER TABLE users ADD COLUMN designation VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN department VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN salary DECIMAL(10, 2) DEFAULT 0.00",

    // 11. Create activity_log table
    "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(50),
        entity_id INT,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )",

    // 12. Create chat_messages table
    "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_pinned BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // 13. Add monthly_payment_date to clients
    "ALTER TABLE clients ADD COLUMN monthly_payment_date VARCHAR(50) DEFAULT NULL"
];

$successCount = 0;
foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        $successCount++;
        echo "Success: " . htmlspecialchars($query) . "<br>";
    } catch (PDOException $e) {
        // Ignored or already applied
        echo "<span style='color:gray'>Skipped/Error: " . htmlspecialchars($query) . " - " . $e->getMessage() . "</span><br>";
    }
}

echo "<br><b>Database update script finished. ($successCount changes applied)</b>";
?>
