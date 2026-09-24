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
        $targetVersion = 10;
        if (!isset($_GET['migrate']) && ($_SESSION['db_migrated_v'] ?? 0) >= $targetVersion) {
            return;
        }

        try {
            $pdo = self::pdo();
        } catch (Throwable $e) {
            return;
        }

        // 1. Leads extra columns
        try {
            $cols = self::getTableColumns($pdo, 'leads');
            if (!in_array('next_step', $cols, true)) $pdo->exec("ALTER TABLE leads ADD COLUMN next_step VARCHAR(255) DEFAULT NULL");
            if (!in_array('notes', $cols, true)) $pdo->exec("ALTER TABLE leads ADD COLUMN notes TEXT DEFAULT NULL");
            if (!in_array('docs_link', $cols, true)) $pdo->exec("ALTER TABLE leads ADD COLUMN docs_link VARCHAR(500) DEFAULT NULL AFTER notes");
            if (!in_array('docs_access', $cols, true)) $pdo->exec("ALTER TABLE leads ADD COLUMN docs_access VARCHAR(50) NOT NULL DEFAULT 'no_access' AFTER docs_link");
        } catch (Throwable $e) {}

        // 1b. Clients deliverable columns
        try {
            $clientCols = self::getTableColumns($pdo, 'clients');
            if (!in_array('reels_required', $clientCols, true)) $pdo->exec("ALTER TABLE clients ADD COLUMN reels_required INT UNSIGNED NOT NULL DEFAULT 0");
            if (!in_array('reels_completed', $clientCols, true)) $pdo->exec("ALTER TABLE clients ADD COLUMN reels_completed INT UNSIGNED NOT NULL DEFAULT 0");
            if (!in_array('posts_required', $clientCols, true)) $pdo->exec("ALTER TABLE clients ADD COLUMN posts_required INT UNSIGNED NOT NULL DEFAULT 0");
            if (!in_array('posts_completed', $clientCols, true)) $pdo->exec("ALTER TABLE clients ADD COLUMN posts_completed INT UNSIGNED NOT NULL DEFAULT 0");
        } catch (Throwable $e) {}

        // 2. Almost closed status
        try {
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM lead_statuses WHERE slug = 'almost-closed' AND folder_id IS NULL")->fetchColumn();
            if ($exists === 0) {
                $closedSort = (int)$pdo->query("SELECT sort_order FROM lead_statuses WHERE slug = 'closed' AND folder_id IS NULL")->fetchColumn();
                $newSort = $closedSort > 0 ? $closedSort : 8;
                $pdo->exec("UPDATE lead_statuses SET sort_order = sort_order + 1 WHERE folder_id IS NULL AND sort_order >= $newSort");
                $pdo->prepare("INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, 0, 0, 0, NULL)")
                    ->execute(['Almost closed', 'almost-closed', '#0ea5e9', $newSort]);
            }
        } catch (Throwable $e) {}

        // 3. Tables creation
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS folder_custom_fields (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, folder_id INT UNSIGNED NOT NULL, field_name VARCHAR(100) NOT NULL, field_type VARCHAR(50) NOT NULL, options TEXT, sort_order INT NOT NULL DEFAULT 0, CONSTRAINT fk_fcf_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS lead_custom_values (lead_id INT UNSIGNED NOT NULL, field_id INT UNSIGNED NOT NULL, field_value TEXT, PRIMARY KEY (lead_id, field_id), CONSTRAINT fk_lcv_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE, CONSTRAINT fk_lcv_field FOREIGN KEY (field_id) REFERENCES folder_custom_fields(id) ON DELETE CASCADE)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, type VARCHAR(50) NOT NULL DEFAULT 'info', created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_ann_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS approvals (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, description TEXT, status VARCHAR(50) NOT NULL DEFAULT 'pending', reviewer_id INT UNSIGNED, reviewer_notes TEXT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_appr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_appr_rev FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS content_calendar (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, content_type VARCHAR(100), post_date DATE NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', assigned_to INT UNSIGNED, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_cc_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE, CONSTRAINT fk_cc_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS operations_issues (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            noticed_by_id INT UNSIGNED NOT NULL,
            responsible_id INT UNSIGNED NOT NULL,
            corrected_by_id INT UNSIGNED DEFAULT NULL,
            correction_notes TEXT DEFAULT NULL,
            deduction_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deduction_type VARCHAR(50) NOT NULL DEFAULT 'deduction',
            client_id INT UNSIGNED DEFAULT NULL,
            task_id INT UNSIGNED DEFAULT NULL,
            content_id INT UNSIGNED DEFAULT NULL,
            severity ENUM('minor', 'medium', 'major', 'critical') NOT NULL DEFAULT 'medium',
            status ENUM('open', 'assigned', 'rectifying', 'corrected', 'escalated_to_founder') NOT NULL DEFAULT 'assigned',
            due_date DATE DEFAULT NULL,
            is_delayed TINYINT(1) NOT NULL DEFAULT 0,
            notified_founder TINYINT(1) NOT NULL DEFAULT 0,
            founder_escalated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            KEY idx_oi_responsible (responsible_id),
            KEY idx_oi_noticed (noticed_by_id),
            KEY idx_oi_corrected (corrected_by_id),
            KEY idx_oi_status (status),
            KEY idx_oi_client (client_id),
            KEY idx_oi_due (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

        // 4. Content Calendar Columns
        try {
            $ccExisting = self::getTableColumns($pdo, 'content_calendar');
            $ccColsToAdd = [
                'content_type' => "VARCHAR(50) NOT NULL DEFAULT 'reel'",
                'service_id' => "INT UNSIGNED DEFAULT NULL",
                'subcategory_id' => "INT UNSIGNED DEFAULT NULL",
                'content' => "TEXT DEFAULT NULL",
                'drive_link' => "VARCHAR(500) DEFAULT NULL",
                'completed_at' => "DATETIME DEFAULT NULL",
                'created_by' => "INT UNSIGNED DEFAULT NULL",
                'editor_checked_by' => "INT UNSIGNED DEFAULT NULL",
                'editor_checked_at' => "DATETIME DEFAULT NULL",
                'manager_reviewed_by' => "INT UNSIGNED DEFAULT NULL",
                'manager_reviewed_at' => "DATETIME DEFAULT NULL",
                'rectification_notes' => "TEXT DEFAULT NULL"
            ];
            foreach ($ccColsToAdd as $col => $colDef) {
                if (!in_array($col, $ccExisting, true)) {
                    try { $pdo->exec("ALTER TABLE content_calendar ADD COLUMN $col $colDef"); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}

        // 5. Approvals Columns
        try {
            $apprCols = self::getTableColumns($pdo, 'approvals');
            if (!in_array('stage', $apprCols, true)) {
                $pdo->exec("ALTER TABLE approvals ADD COLUMN stage VARCHAR(50) NOT NULL DEFAULT 'pending_editor'");
            }
            if (!in_array('editor_id', $apprCols, true)) {
                $pdo->exec("ALTER TABLE approvals ADD COLUMN editor_id INT UNSIGNED DEFAULT NULL");
            }
            if (!in_array('editor_notes', $apprCols, true)) {
                $pdo->exec("ALTER TABLE approvals ADD COLUMN editor_notes TEXT DEFAULT NULL");
            }
            if (!in_array('rectification_notes', $apprCols, true)) {
                $pdo->exec("ALTER TABLE approvals ADD COLUMN rectification_notes TEXT DEFAULT NULL");
            }
        } catch (Throwable $e) {}

        // 6. Ensure Sheet 1 to Sheet 7 folders exist and are accessible
        try {
            $existingFolders = $pdo->query("SELECT name FROM lead_folders")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $founderId = (int)($pdo->query("SELECT id FROM users WHERE is_founder = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
            
            for ($i = 1; $i <= 7; $i++) {
                $sheetName = "Sheet $i";
                if (!in_array($sheetName, $existingFolders, true)) {
                    $stmt = $pdo->prepare("INSERT INTO lead_folders (name, created_by, created_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$sheetName, $founderId]);
                    $newFolderId = (int)$pdo->lastInsertId();
                    
                    // Seed standard statuses for this sheet
                    $defaultStatuses = [
                        ['New Leads', 'new-leads', '#6366f1', 1],
                        ['Contacted', 'contacted', '#0ea5e9', 2],
                        ['In Discussion', 'in-discussion', '#f59e0b', 3],
                        ['Follow Up', 'follow-up', '#ec4899', 4],
                        ['Meeting Scheduled', 'meeting-scheduled', '#8b5cf6', 5],
                        ['Almost closed', 'almost-closed', '#0284c7', 6],
                        ['Closed', 'closed', '#10b981', 7],
                        ['Lost', 'lost', '#ef4444', 8],
                    ];
                    foreach ($defaultStatuses as $ds) {
                        try {
                            $pdo->prepare("INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$ds[0], $ds[1], $ds[2], $ds[3], ($ds[1]==='closed'?1:0), ($ds[1]==='lost'?1:0), ($ds[1]==='new-leads'?1:0), $newFolderId]);
                        } catch (Throwable $e) {}
                    }
                }
            }
        } catch (Throwable $e) {}

        $_SESSION['db_migrated_v'] = $targetVersion;
    }

    public static function getTableColumns(PDO $pdo, string $table): array
    {
        try {
            $res = $pdo->query("SHOW COLUMNS FROM `$table`");
            if ($res) {
                return array_column($res->fetchAll(PDO::FETCH_ASSOC), 'Field');
            }
        } catch (Throwable $e) {}
        return [];
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
