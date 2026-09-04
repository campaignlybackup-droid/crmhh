<?php
require_once 'functions.php';
requireLogin();

// Fetch list of all tables in database
$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tables = [
        'users', 'clients', 'projects', 'leads', 'tasks', 'invoices',
        'lead_history', 'notifications', 'content_calendar', 'chat_messages',
        'departments', 'comments', 'documents', 'checklists', 'project_stages',
        'attendance', 'leave_requests', 'company_holidays', 'approvals',
        'meetings', 'calendar_events', 'workflow_templates', 'workflow_tasks', 'daily_work'
    ];
}

// Fetch all users
$users = [];
try {
    $users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

/**
 * Helper function to extract all data linked to a specific user_id across all CRM tables
 */
function fetchUserData($pdo, $userId) {
    $userData = [];
    
    // User info
    $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData['user_info'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Clients assigned
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE assigned_to = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $userData['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Projects created or assigned
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE (created_by = ? OR assigned_to = ?) AND deleted_at IS NULL");
    $stmt->execute([$userId, $userId]);
    $userData['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Leads assigned
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE assigned_to = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $userData['leads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tasks created or assigned or reviewed
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE (created_by = ? OR assigned_to = ? OR reviewer_id = ?) AND deleted_at IS NULL");
    $stmt->execute([$userId, $userId, $userId]);
    $userData['tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Invoices assigned
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE assigned_to = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $userData['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Daily work logs
    try {
        $stmt = $pdo->prepare("SELECT * FROM daily_work WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userData['daily_work'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userData['daily_work'] = [];
    }

    // Content calendar assigned
    $stmt = $pdo->prepare("SELECT * FROM content_calendar WHERE assigned_to = ?");
    $stmt->execute([$userId]);
    $userData['content_calendar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lead history activity
    $stmt = $pdo->prepare("SELECT * FROM lead_history WHERE changed_by = ?");
    $stmt->execute([$userId]);
    $userData['lead_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Comments
    try {
        $stmt = $pdo->prepare("SELECT * FROM comments WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userData['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userData['comments'] = [];
    }

    // Chat messages
    try {
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userData['chat_messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userData['chat_messages'] = [];
    }

    // Attendance
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userData['attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userData['attendance'] = [];
    }

    // Leave requests
    try {
        $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? OR manager_id = ? OR admin_id = ?");
        $stmt->execute([$userId, $userId, $userId]);
        $userData['leave_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userData['leave_requests'] = [];
    }

    return $userData;
}

$action = $_GET['action'] ?? '';

// --- ACTION: EXPORT TARGET SUPABASE CRM SQL ---
if ($action === 'export_target_supabase_sql') {
    require_once 'generate_target_crm_sql.php';
    exit;
}

// --- ACTION: EXPORT MASTER ALL USERS GROUPED JSON ---
if ($action === 'export_grouped_users_json') {
    $filename = 'crm_all_users_data_export_' . date('Y-m-d_H-i-s') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $masterExport = [
        'exported_at' => date('Y-m-d H:i:s'),
        'total_users' => count($users),
        'user_data' => []
    ];

    foreach ($users as $u) {
        $masterExport['user_data'][$u['username']] = fetchUserData($pdo, $u['id']);
    }

    echo json_encode($masterExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- ACTION: EXPORT SPECIFIC USER JSON ---
if ($action === 'export_user_json') {
    $userId = (int)($_GET['user_id'] ?? 0);
    $userData = fetchUserData($pdo, $userId);
    $username = $userData['user_info']['username'] ?? ('user_' . $userId);

    $filename = 'data_added_by_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $username) . '_' . date('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo json_encode([
        'exported_at' => date('Y-m-d H:i:s'),
        'user' => $userData['user_info'],
        'data' => $userData
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- ACTION: EXPORT SPECIFIC USER SQL ---
if ($action === 'export_user_sql') {
    $userId = (int)($_GET['user_id'] ?? 0);
    $userData = fetchUserData($pdo, $userId);
    $username = $userData['user_info']['username'] ?? ('user_' . $userId);

    $filename = 'data_added_by_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $username) . '_' . date('Y-m-d') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- Data added / assigned to User: {$username} (ID: {$userId})\n";
    echo "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($userData as $tbl => $rows) {
        if ($tbl === 'user_info' || empty($rows) || !is_array($rows)) continue;
        echo "-- Table: `{$tbl}` (" . count($rows) . " rows)\n";
        $first = reset($rows);
        if (!is_array($first)) continue;
        $columns = array_keys($first);
        $colNames = "`" . implode("`, `", $columns) . "`";

        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($row));
            echo "INSERT INTO `{$tbl}` ({$colNames}) VALUES (" . implode(", ", $vals) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// --- ACTION: EXPORT FULL SQL ---
if ($action === 'export_sql') {
    $filename = 'crm_data_export_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- Full CRM SQL Export\n";
    echo "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            echo "-- Table: `$table` (" . count($rows) . " rows)\n";
            $columns = array_keys($rows[0]);
            $colNames = "`" . implode("`, `", $columns) . "`";

            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    return $val === null ? "NULL" : $pdo->quote($val);
                }, array_values($row));

                echo "INSERT INTO `$table` ($colNames) VALUES (" . implode(", ", $values) . ");\n";
            }
            echo "\n";
        } catch (PDOException $e) {}
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// --- ACTION: EXPORT FULL JSON ---
if ($action === 'export_json') {
    $filename = 'crm_data_export_' . date('Y-m-d_H-i-s') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $exportData = ['exported_at' => date('Y-m-d H:i:s'), 'tables' => []];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $exportData['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $exportData['tables'][$table] = [];
        }
    }

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- ACTION: EXPORT TABLE CSV ---
if ($action === 'export_csv') {
    $table = $_GET['table'] ?? '';
    if (!in_array($table, $tables)) die("Invalid table.");

    $filename = $table . '_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    try {
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $first = true;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($first) {
                fputcsv($output, array_keys($row));
                $first = false;
            }
            fputcsv($output, array_values($row));
        }
    } catch (PDOException $e) {}
    fclose($output);
    exit;
}

// Compute metrics per user for UI
$userMetrics = [];
foreach ($users as $u) {
    $uid = $u['id'];
    $uData = fetchUserData($pdo, $uid);

    $totalItems = 0;
    foreach ($uData as $k => $arr) {
        if ($k !== 'user_info' && is_array($arr)) {
            $totalItems += count($arr);
        }
    }

    $userMetrics[] = [
        'info' => $u,
        'counts' => [
            'clients' => count($uData['clients'] ?? []),
            'projects' => count($uData['projects'] ?? []),
            'leads' => count($uData['leads'] ?? []),
            'tasks' => count($uData['tasks'] ?? []),
            'invoices' => count($uData['invoices'] ?? []),
            'daily_work' => count($uData['daily_work'] ?? []),
            'total' => $totalItems
        ]
    ];
}

require_once 'header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-box-arrow-up-right me-2 text-primary"></i> Data Migration & User Export Hub</h2>
            <p class="text-muted mb-0">Transfer all CRM data grouped by each user or as full database backups.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export_all_data.php?action=export_target_supabase_sql" class="btn text-white fw-semibold" style="background-color: #6366f1;">
                <i class="bi bi-rocket-takeoff me-1"></i> Target Supabase CRM SQL (.sql)
            </a>
            <a href="export_all_data.php?action=export_grouped_users_json" class="btn btn-success fw-semibold">
                <i class="bi bi-people-fill me-1"></i> Export Master JSON (All Users)
            </a>
            <a href="export_all_data.php?action=export_sql" class="btn btn-primary fw-semibold">
                <i class="bi bi-filetype-sql me-1"></i> Full MySQL Dump (.sql)
            </a>
        </div>
    </div>

    <!-- User-by-User Data Transfer Cards -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-bold py-3 fs-5 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-lines-fill text-primary me-2"></i> Data Added / Assigned by Each User</span>
            <span class="badge bg-primary rounded-pill"><?= count($users) ?> Team Users</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">User</th>
                            <th>Role</th>
                            <th>Clients</th>
                            <th>Projects</th>
                            <th>Leads</th>
                            <th>Tasks</th>
                            <th>Invoices</th>
                            <th>Work Logs</th>
                            <th class="fw-bold">Total Records</th>
                            <th class="text-end pe-3">Export User Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($userMetrics)): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">No users found in database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($userMetrics as $m): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2 fw-bold" style="width: 34px; height: 34px;">
                                            <?= strtoupper(substr($m['info']['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= h($m['info']['username']) ?></div>
                                            <div class="small text-muted">ID: #<?= $m['info']['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark text-capitalize border"><?= h($m['info']['role']) ?></span></td>
                                <td><span class="badge bg-info-subtle text-info fw-bold"><?= number_format($m['counts']['clients']) ?></span></td>
                                <td><span class="badge bg-primary-subtle text-primary fw-bold"><?= number_format($m['counts']['projects']) ?></span></td>
                                <td><span class="badge bg-warning-subtle text-warning fw-bold"><?= number_format($m['counts']['leads']) ?></span></td>
                                <td><span class="badge bg-success-subtle text-success fw-bold"><?= number_format($m['counts']['tasks']) ?></span></td>
                                <td><span class="badge bg-danger-subtle text-danger fw-bold"><?= number_format($m['counts']['invoices']) ?></span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary fw-bold"><?= number_format($m['counts']['daily_work']) ?></span></td>
                                <td><span class="fs-6 fw-bold text-dark"><?= number_format($m['counts']['total']) ?></span></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="export_all_data.php?action=export_user_json&user_id=<?= $m['info']['id'] ?>" class="btn btn-outline-dark" title="Download JSON for this user">
                                            <i class="bi bi-filetype-json me-1"></i> JSON
                                        </a>
                                        <a href="export_all_data.php?action=export_user_sql&user_id=<?= $m['info']['id'] ?>" class="btn btn-outline-primary" title="Download SQL for this user">
                                            <i class="bi bi-filetype-sql me-1"></i> SQL
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Main Global Exports -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-bold py-3 fs-5">
                    <i class="bi bi-database-fill-down text-primary me-2"></i> Full System Database Backups
                </div>
                <div class="card-body">
                    <p class="text-muted">Export the entire CRM database across all tables and all users in one file.</p>
                    <div class="d-grid gap-2">
                        <a href="export_all_data.php?action=export_sql" class="btn btn-outline-primary py-2 fw-semibold">
                            <i class="bi bi-file-earmark-code fs-5 me-2 align-middle"></i> Download Full SQL File (.sql)
                        </a>
                        <a href="export_all_data.php?action=export_json" class="btn btn-outline-dark py-2 fw-semibold">
                            <i class="bi bi-file-earmark-font fs-5 me-2 align-middle"></i> Download Full JSON File (.json)
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-bold py-3 fs-5">
                    <i class="bi bi-file-earmark-excel text-success me-2"></i> Individual Table CSV Spreadsheets
                </div>
                <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        <?php foreach ($tables as $tbl): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="font-monospace fw-bold small"><?= h($tbl) ?></span>
                            <a href="export_all_data.php?action=export_csv&table=<?= urlencode($tbl) ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download me-1"></i> CSV
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
