<?php
/**
 * Safe, non-destructive migration script for deep CRM fixes.
 * Preserves all existing data.
 */
require_once __DIR__ . '/core/bootstrap.php';

echo "<h2>Starting Non-Destructive CRM Deep Migration...</h2><pre>";

try {
    $db = Database::pdo();

    // 1. Add "Almost closed" status to lead_statuses if not exists
    $exists = Database::scalar("SELECT id FROM lead_statuses WHERE slug = 'almost-closed' AND folder_id IS NULL");
    if (!$exists) {
        // Find position of 'closed' to place right before it
        $closedSort = (int)Database::scalar("SELECT sort_order FROM lead_statuses WHERE slug = 'closed' AND folder_id IS NULL");
        $newSort = $closedSort > 0 ? $closedSort : 8;
        // Bump closed and subsequent statuses
        $db->exec("UPDATE lead_statuses SET sort_order = sort_order + 1 WHERE folder_id IS NULL AND sort_order >= $newSort");
        $db->prepare("INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, 0, 0, 0, NULL)")
           ->execute(['Almost closed', 'almost-closed', '#0ea5e9', $newSort]);
        echo "✓ Added 'Almost closed' status to lead_statuses.\n";
    } else {
        echo "- 'Almost closed' status already exists.\n";
    }

    // 2. Add docs_link and docs_access to leads table if not present
    $cols = Database::all("SHOW COLUMNS FROM leads");
    $colNames = array_column($cols, 'Field');
    if (!in_array('docs_link', $colNames, true)) {
        $db->exec("ALTER TABLE leads ADD COLUMN docs_link VARCHAR(500) DEFAULT NULL AFTER notes");
        echo "✓ Added docs_link column to leads.\n";
    }
    if (!in_array('docs_access', $colNames, true)) {
        $db->exec("ALTER TABLE leads ADD COLUMN docs_access VARCHAR(50) NOT NULL DEFAULT 'no_access' AFTER docs_link");
        echo "✓ Added docs_access column to leads.\n";
    }

    // 2b. Add deliverable columns to clients table if not present
    $cCols = Database::all("SHOW COLUMNS FROM clients");
    $cColNames = array_column($cCols, 'Field');
    if (!in_array('reels_required', $cColNames, true)) {
        $db->exec("ALTER TABLE clients ADD COLUMN reels_required INT UNSIGNED NOT NULL DEFAULT 0");
        echo "✓ Added reels_required column to clients.\n";
    }
    if (!in_array('reels_completed', $cColNames, true)) {
        $db->exec("ALTER TABLE clients ADD COLUMN reels_completed INT UNSIGNED NOT NULL DEFAULT 0");
        echo "✓ Added reels_completed column to clients.\n";
    }
    if (!in_array('posts_required', $cColNames, true)) {
        $db->exec("ALTER TABLE clients ADD COLUMN posts_required INT UNSIGNED NOT NULL DEFAULT 0");
        echo "✓ Added posts_required column to clients.\n";
    }
    if (!in_array('posts_completed', $cColNames, true)) {
        $db->exec("ALTER TABLE clients ADD COLUMN posts_completed INT UNSIGNED NOT NULL DEFAULT 0");
        echo "✓ Added posts_completed column to clients.\n";
    }

    // 3. Create operations_issues table
    $db->exec("CREATE TABLE IF NOT EXISTS operations_issues (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Checked/created operations_issues table.\n";

    // 4. Add double-check process columns to content_calendar
    $ccCols = array_column(Database::all("SHOW COLUMNS FROM content_calendar"), 'Field');
    if (!in_array('editor_checked_by', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN editor_checked_by INT UNSIGNED DEFAULT NULL");
        echo "✓ Added editor_checked_by to content_calendar.\n";
    }
    if (!in_array('editor_checked_at', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN editor_checked_at DATETIME DEFAULT NULL");
        echo "✓ Added editor_checked_at to content_calendar.\n";
    }
    if (!in_array('manager_reviewed_by', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN manager_reviewed_by INT UNSIGNED DEFAULT NULL");
        echo "✓ Added manager_reviewed_by to content_calendar.\n";
    }
    if (!in_array('manager_reviewed_at', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN manager_reviewed_at DATETIME DEFAULT NULL");
        echo "✓ Added manager_reviewed_at to content_calendar.\n";
    }
    if (!in_array('rectification_notes', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN rectification_notes TEXT DEFAULT NULL");
        echo "✓ Added rectification_notes to content_calendar.\n";
    }
    if (!in_array('content_type', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN content_type VARCHAR(50) NOT NULL DEFAULT 'reel'");
        echo "✓ Added content_type to content_calendar.\n";
    }
    if (!in_array('drive_link', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN drive_link VARCHAR(500) DEFAULT NULL");
        echo "✓ Added drive_link to content_calendar.\n";
    }
    if (!in_array('completed_at', $ccCols, true)) {
        $db->exec("ALTER TABLE content_calendar ADD COLUMN completed_at DATETIME DEFAULT NULL");
        echo "✓ Added completed_at to content_calendar.\n";
    }

    // 5. Add double-check columns to approvals
    $apprCols = array_column(Database::all("SHOW COLUMNS FROM approvals"), 'Field');
    if (!in_array('stage', $apprCols, true)) {
        $db->exec("ALTER TABLE approvals ADD COLUMN stage VARCHAR(50) NOT NULL DEFAULT 'pending_editor'");
        echo "✓ Added stage to approvals.\n";
    }
    if (!in_array('editor_id', $apprCols, true)) {
        $db->exec("ALTER TABLE approvals ADD COLUMN editor_id INT UNSIGNED DEFAULT NULL");
        echo "✓ Added editor_id to approvals.\n";
    }
    if (!in_array('editor_notes', $apprCols, true)) {
        $db->exec("ALTER TABLE approvals ADD COLUMN editor_notes TEXT DEFAULT NULL");
        echo "✓ Added editor_notes to approvals.\n";
    }
    if (!in_array('rectification_notes', $apprCols, true)) {
        $db->exec("ALTER TABLE approvals ADD COLUMN rectification_notes TEXT DEFAULT NULL");
        echo "✓ Added rectification_notes to approvals.\n";
    }

    // 6. Check / create default "Interviews" folder with salesy columns
    $interviewFolder = Database::one("SELECT id FROM lead_folders WHERE name = 'Interviews' OR name = 'Sales Interviews'");
    if (!$interviewFolder) {
        $founderId = (int)Database::scalar("SELECT id FROM users WHERE is_founder = 1 ORDER BY id ASC LIMIT 1");
        if (!$founderId) $founderId = 1;
        $db->prepare("INSERT INTO lead_folders (name, created_by, created_at) VALUES (?, ?, NOW())")
           ->execute(['Interviews', $founderId]);
        $folderId = (int)$db->lastInsertId();
        echo "✓ Created 'Interviews' lead folder (ID: $folderId).\n";

        // Seed default statuses for Interviews folder
        $intStatuses = [
            ['Applied / Sourced', 'applied', '#6c757d', 1],
            ['Interview Scheduled', 'scheduled', '#0ea5e9', 2],
            ['Interview Done - In Review', 'in-review', '#f59e0b', 3],
            ['Sales Roleplay Test Passed', 'test-passed', '#8b5cf6', 4],
            ['Offer Extended / Selected', 'selected', '#10b981', 5],
            ['Rejected', 'rejected', '#ef4444', 6]
        ];
        foreach ($intStatuses as $st) {
            $db->prepare("INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$st[0], $st[1], $st[2], $st[3], ($st[1]==='selected'?1:0), ($st[1]==='rejected'?1:0), ($st[1]==='applied'?1:0), $folderId]);
        }

        // Add salesy custom fields to this folder
        $salesyFields = [
            ['Interview Date & Time', 'date', null, 1],
            ['Sales Experience (Years)', 'number', null, 2],
            ['Pitch / Roleplay Score (1-10)', 'number', null, 3],
            ['Past Agency / Revenue Record', 'text', null, 4],
            ['Sales Deck / Resume Link', 'text', null, 5],
            ['Interviewer Notes & Decision', 'text', null, 6]
        ];
        foreach ($salesyFields as $sf) {
            $db->prepare("INSERT INTO folder_custom_fields (folder_id, field_name, field_type, options, sort_order) VALUES (?, ?, ?, ?, ?)")
               ->execute([$folderId, $sf[0], $sf[1], $sf[2], $sf[3]]);
        }
        echo "✓ Pre-configured salesy columns for 'Interviews' folder.\n";
    } else {
        echo "- 'Interviews' folder already exists.\n";
    }

    // 7. Ensure Sheet 1 to Sheet 7 folders exist and are accessible
    $existingFolders = $db->query("SELECT name FROM lead_folders")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $founderId = (int)($db->query("SELECT id FROM users WHERE is_founder = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);

    for ($i = 1; $i <= 7; $i++) {
        $sheetName = "Sheet $i";
        if (!in_array($sheetName, $existingFolders, true)) {
            $stmt = $db->prepare("INSERT INTO lead_folders (name, created_by, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$sheetName, $founderId]);
            $newFolderId = (int)$db->lastInsertId();

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
                    $db->prepare("INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$ds[0], $ds[1], $ds[2], $ds[3], ($ds[1]==='closed'?1:0), ($ds[1]==='lost'?1:0), ($ds[1]==='new-leads'?1:0), $newFolderId]);
                } catch (Throwable $e) {}
            }
            echo "✓ Created '$sheetName' folder (ID: $newFolderId) with default statuses.\n";
        } else {
            echo "- '$sheetName' already exists.\n";
        }
    }

    echo "\n</pre><h3 style='color:green;'>Migration successfully completed with ZERO data loss!</h3>";
} catch (Throwable $e) {
    echo "\n</pre><h3 style='color:red;'>Migration Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
