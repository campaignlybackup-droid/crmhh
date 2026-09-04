<?php
/**
 * Command-line user data exporter for CRM database.
 * Usage:
 *   php export_cli.php --grouped_users=1
 *   php export_cli.php --user_id=2 --format=sql|json
 *   php export_cli.php --format=sql|json|csv
 */

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

$options = getopt('', ['format::', 'out::', 'user_id::', 'grouped_users::']);
$format = strtolower($options['format'] ?? 'sql');
$outDir = $options['out'] ?? __DIR__ . '/exports';
$filterUserId = isset($options['user_id']) ? (int)$options['user_id'] : null;
$groupedUsers = isset($options['grouped_users']);

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

function fetchUserDataCli($pdo, $userId) {
    $data = [];
    $data['user_info'] = $pdo->query("SELECT id, username, role FROM users WHERE id = {$userId}")->fetch(PDO::FETCH_ASSOC);
    
    $queries = [
        'clients' => "SELECT * FROM clients WHERE assigned_to = {$userId}",
        'projects' => "SELECT * FROM projects WHERE created_by = {$userId} OR assigned_to = {$userId}",
        'leads' => "SELECT * FROM leads WHERE assigned_to = {$userId}",
        'tasks' => "SELECT * FROM tasks WHERE created_by = {$userId} OR assigned_to = {$userId} OR reviewer_id = {$userId}",
        'invoices' => "SELECT * FROM invoices WHERE assigned_to = {$userId}",
        'daily_work' => "SELECT * FROM daily_work WHERE user_id = {$userId}",
        'content_calendar' => "SELECT * FROM content_calendar WHERE assigned_to = {$userId}",
        'lead_history' => "SELECT * FROM lead_history WHERE changed_by = {$userId}",
        'chat_messages' => "SELECT * FROM chat_messages WHERE user_id = {$userId}",
        'comments' => "SELECT * FROM comments WHERE user_id = {$userId}",
        'attendance' => "SELECT * FROM attendance WHERE user_id = {$userId}",
        'leave_requests' => "SELECT * FROM leave_requests WHERE user_id = {$userId} OR manager_id = {$userId} OR admin_id = {$userId}"
    ];

    foreach ($queries as $key => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $data[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data[$key] = [];
        }
    }
    return $data;
}

if ($groupedUsers) {
    echo "Exporting data grouped by all users...\n";
    $users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $master = ['exported_at' => date('Y-m-d H:i:s'), 'users' => []];
    foreach ($users as $u) {
        $master['users'][$u['username']] = fetchUserDataCli($pdo, $u['id']);
        echo "  - Exported data for user '{$u['username']}' (ID: {$u['id']})\n";
    }
    $outFile = $outDir . '/crm_all_users_grouped_' . date('Y-m-d_His') . '.json';
    file_put_contents($outFile, json_encode($master, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nSuccess! Master grouped JSON created at: {$outFile}\n";
    exit;
}

if ($filterUserId) {
    echo "Exporting data for User ID #{$filterUserId}...\n";
    $userData = fetchUserDataCli($pdo, $filterUserId);
    $uname = $userData['user_info']['username'] ?? ('user_' . $filterUserId);

    if ($format === 'json') {
        $outFile = $outDir . "/user_{$uname}_export_" . date('Y-m-d_His') . ".json";
        file_put_contents($outFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        $outFile = $outDir . "/user_{$uname}_export_" . date('Y-m-d_His') . ".sql";
        $sqlContent = "-- Export for User: {$uname} (ID: {$filterUserId})\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach ($userData as $tbl => $rows) {
            if ($tbl === 'user_info' || empty($rows)) continue;
            $sqlContent .= "-- Table: `{$tbl}` (" . count($rows) . " rows)\n";
            $cols = "`" . implode("`, `", array_keys($rows[0])) . "`";
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($row));
                $sqlContent .= "INSERT INTO `{$tbl}` ({$cols}) VALUES (" . implode(", ", $vals) . ");\n";
            }
            $sqlContent .= "\n";
        }
        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        file_put_contents($outFile, $sqlContent);
    }
    echo "Success! User export created at: {$outFile}\n";
    exit;
}

// Fallback default system export
echo "Running standard full export...\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($format === 'json') {
    $export = ['exported_at' => date('Y-m-d H:i:s'), 'tables' => []];
    foreach ($tables as $tbl) {
        $export['tables'][$tbl] = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
    }
    $outFile = $outDir . '/crm_data_export_' . date('Y-m-d_His') . '.json';
    file_put_contents($outFile, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Success! JSON export created at: {$outFile}\n";
} else {
    $outFile = $outDir . '/full_crm_export_' . date('Y-m-d_His') . '.sql';
    $sqlContent = "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach ($tables as $tbl) {
        $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) continue;
        $cols = "`" . implode("`, `", array_keys($rows[0])) . "`";
        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($row));
            $sqlContent .= "INSERT INTO `$tbl` ($cols) VALUES (" . implode(", ", $vals) . ");\n";
        }
    }
    $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents($outFile, $sqlContent);
    echo "Success! SQL export created at: {$outFile}\n";
}
