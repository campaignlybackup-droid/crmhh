<?php

class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $cfg = config('db');
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                app_log('DB connection failed: ' . $e->getMessage());
                fatal_error('Unable to connect to the database. Please check the configuration or contact the administrator.');
            }
        }
        return self::$instance;
    }
    public static function autoMigrate(): void
    {
        // Only run once per session to avoid overhead
        if (isset($_SESSION['db_migrated'])) return;
        
        try {
            $pdo = self::pdo();
            
            // Check leads columns
            $stmt = $pdo->query("SHOW COLUMNS FROM leads LIKE 'next_step'");
            if ($stmt->rowCount() === 0) $pdo->exec("ALTER TABLE leads ADD COLUMN next_step VARCHAR(255) DEFAULT NULL");
            
            $stmt = $pdo->query("SHOW COLUMNS FROM leads LIKE 'notes'");
            if ($stmt->rowCount() === 0) $pdo->exec("ALTER TABLE leads ADD COLUMN notes TEXT DEFAULT NULL");
            
            // Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS folder_custom_fields (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, folder_id INT UNSIGNED NOT NULL, field_name VARCHAR(100) NOT NULL, field_type VARCHAR(50) NOT NULL, options TEXT, sort_order INT NOT NULL DEFAULT 0, CONSTRAINT fk_fcf_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS lead_custom_values (lead_id INT UNSIGNED NOT NULL, field_id INT UNSIGNED NOT NULL, field_value TEXT, PRIMARY KEY (lead_id, field_id), CONSTRAINT fk_lcv_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE, CONSTRAINT fk_lcv_field FOREIGN KEY (field_id) REFERENCES folder_custom_fields(id) ON DELETE CASCADE)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, type VARCHAR(50) NOT NULL DEFAULT 'info', created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_ann_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS approvals (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, description TEXT, status VARCHAR(50) NOT NULL DEFAULT 'pending', reviewer_id INT UNSIGNED, reviewer_notes TEXT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_appr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_appr_rev FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS content_calendar (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, content_type VARCHAR(100), post_date DATE NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', assigned_to INT UNSIGNED, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_cc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE, CONSTRAINT fk_cc_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL)");
            
            $_SESSION['db_migrated'] = true;
        } catch (Exception $e) {
            // Silently ignore schema creation errors here, assume they are handled by the app
        }
    }

    /** Run a SELECT and return all rows. */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a SELECT and return the first row, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Run a SELECT and return a single scalar value. */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /** Run an INSERT/UPDATE/DELETE, return affected row count. */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    public static function beginTransaction(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollBack(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }
}
