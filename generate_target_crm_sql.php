<?php
/**
 * Target CRM Migration SQL Generator
 * Transforms PHP CRM (MySQL) data into PostgreSQL / Supabase INSERT statements
 * matching the schema of target Next.js Supabase CRM.
 */

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

$outputSql = "-- =====================================================================\n";
$outputSql .= "-- MIGRATION SQL FOR TARGET NEXT.JS + SUPABASE CRM\n";
$outputSql .= "-- Source: PHP CRM (" . DB_NAME . ")\n";
$outputSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$outputSql .= "-- =====================================================================\n\n";

// Helper to generate UUID v4 deterministically from integer ID and namespace
function makeUuid($namespace, $id) {
    $hash = md5($namespace . '_' . $id);
    return sprintf('%08s-%04s-%04s-%04s-%12s',
        substr($hash, 0, 8),
        substr($hash, 8, 4),
        substr($hash, 12, 4),
        substr($hash, 16, 4),
        substr($hash, 20, 12)
    );
}

// 1. MIGRATING USERS
try {
    $users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $outputSql .= "-- ---------------------------------------------------------------------\n";
    $outputSql .= "-- Users Migration (" . count($users) . " records)\n";
    $outputSql .= "-- ---------------------------------------------------------------------\n";

    foreach ($users as $u) {
        $userId = makeUuid('user', $u['id']);
        $authId = makeUuid('auth', $u['id']);
        $fullName = addslashes($u['username']);
        $email = addslashes(strtolower($u['username']) . '@agency.com');
        $roleCode = ($u['role'] === 'superadmin') ? 'FOUNDER' : (($u['role'] === 'manager') ? 'ACCOUNT_MANAGER' : 'VIDEO_EDITOR');
        
        $outputSql .= "INSERT INTO public.users (id, auth_id, full_name, email, role_id, status, timezone, joined_on)\n";
        $outputSql .= "SELECT '{$userId}'::uuid, '{$authId}'::uuid, '{$fullName}', '{$email}',\n";
        $outputSql .= "       (SELECT id FROM public.roles WHERE code = '{$roleCode}' LIMIT 1), 'Active'::user_status, 'Asia/Dubai', CURRENT_DATE\n";
        $outputSql .= "ON CONFLICT (id) DO UPDATE SET full_name = EXCLUDED.full_name;\n\n";
    }
} catch (Exception $e) {
    $outputSql .= "-- Error reading users: " . $e->getMessage() . "\n\n";
}

// 2. MIGRATING CLIENTS
try {
    $clients = $pdo->query("SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $outputSql .= "-- ---------------------------------------------------------------------\n";
    $outputSql .= "-- Clients Migration (" . count($clients) . " records)\n";
    $outputSql .= "-- ---------------------------------------------------------------------\n";

    foreach ($clients as $c) {
        $clientId = makeUuid('client', $c['id']);
        $brandName = addslashes($c['client_name']);
        $status = ($c['status'] === 'Active') ? 'Active' : (($c['status'] === 'Churned') ? 'Churned' : 'Active');
        $amId = $c['assigned_to'] ? "'" . makeUuid('user', $c['assigned_to']) . "'::uuid" : "NULL";
        $onboarding = $c['onboarding_date'] ? "'{$c['onboarding_date']}'" : "CURRENT_DATE";

        $outputSql .= "INSERT INTO public.clients (id, legal_name, brand_name, status, onboarding_date, account_manager_id)\n";
        $outputSql .= "VALUES ('{$clientId}'::uuid, '{$brandName}', '{$brandName}', '{$status}'::client_status, {$onboarding}, {$amId})\n";
        $outputSql .= "ON CONFLICT (id) DO NOTHING;\n\n";
    }
} catch (Exception $e) {
    $outputSql .= "-- Error reading clients: " . $e->getMessage() . "\n\n";
}

// 3. MIGRATING LEADS
try {
    $leads = $pdo->query("SELECT * FROM leads WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $outputSql .= "-- ---------------------------------------------------------------------\n";
    $outputSql .= "-- Leads Migration (" . count($leads) . " records)\n";
    $outputSql .= "-- ---------------------------------------------------------------------\n";

    foreach ($leads as $l) {
        $leadId = makeUuid('lead', $l['id']);
        $leadName = addslashes($l['name']);
        $contactName = addslashes($l['contact_name'] ?? $l['name']);
        $phone = addslashes($l['phone'] ?? '');
        $email = addslashes($l['email'] ?? '');
        $dealValue = floatval($l['deal_value'] ?? 0);
        $ownerId = $l['assigned_to'] ? "'" . makeUuid('user', $l['assigned_to']) . "'::uuid" : "NULL";

        $outputSql .= "INSERT INTO public.leads (id, title, contact_name, phone, email, estimated_value, assigned_to_id)\n";
        $outputSql .= "VALUES ('{$leadId}'::uuid, '{$leadName}', '{$contactName}', '{$phone}', '{$email}', {$dealValue}, {$ownerId})\n";
        $outputSql .= "ON CONFLICT (id) DO NOTHING;\n\n";
    }
} catch (Exception $e) {
    $outputSql .= "-- Error reading leads: " . $e->getMessage() . "\n\n";
}

// 4. MIGRATING PROJECTS
try {
    $projects = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $outputSql .= "-- ---------------------------------------------------------------------\n";
    $outputSql .= "-- Projects Migration (" . count($projects) . " records)\n";
    $outputSql .= "-- ---------------------------------------------------------------------\n";

    foreach ($projects as $p) {
        $projectId = makeUuid('project', $p['id']);
        $projectName = addslashes($p['project_name']);
        $clientId = $p['client_id'] ? "'" . makeUuid('client', $p['client_id']) . "'::uuid" : "NULL";
        $createdById = $p['created_by'] ? "'" . makeUuid('user', $p['created_by']) . "'::uuid" : "NULL";

        $outputSql .= "INSERT INTO public.projects (id, title, client_id, created_by)\n";
        $outputSql .= "VALUES ('{$projectId}'::uuid, '{$projectName}', {$clientId}, {$createdById})\n";
        $outputSql .= "ON CONFLICT (id) DO NOTHING;\n\n";
    }
} catch (Exception $e) {
    $outputSql .= "-- Error reading projects: " . $e->getMessage() . "\n\n";
}

// Save generated SQL file
$outFile = __DIR__ . '/target_crm_supabase_migration.sql';
file_put_contents($outFile, $outputSql);

if (php_sapi_name() === 'cli') {
    echo "Successfully generated target CRM migration SQL file at:\n{$outFile}\n";
} else {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="target_crm_supabase_migration.sql"');
    echo $outputSql;
    exit;
}
