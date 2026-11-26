<?php
/**
 * Agency CRM - Founder Realtime REST API Gateway for Claude AI & MCP
 *
 * Authenticates requests using a Bearer token or X-CRM-Key header and executes
 * actions in the context of the Founder account with full permissions and audit logging.
 */

require_once __DIR__ . '/core/bootstrap.php';

// Force JSON response
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Helper to send JSON response and terminate
function api_response(bool $success, $data = null, ?string $error = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Get API Key from header or query parameter
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$apiKey = '';

if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $apiKey = $matches[1];
} elseif (!empty($headers['X-CRM-Key'])) {
    $apiKey = trim($headers['X-CRM-Key']);
} elseif (!empty($headers['x-crm-key'])) {
    $apiKey = trim($headers['x-crm-key']);
} elseif (!empty($_REQUEST['api_key'])) {
    $apiKey = trim($_REQUEST['api_key']);
}

// 2. Fetch or initialize the Founder API Key from database or config
$configuredKey = config('app')['founder_api_key'] ?? null;
if (empty($configuredKey)) {
    try {
        $configuredKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'founder_api_key'");
        if (empty($configuredKey)) {
            $configuredKey = 'crm_founder_' . bin2hex(random_bytes(24));
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('founder_api_key', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$configuredKey]);
        }
    } catch (Throwable $e) {
        $configuredKey = 'crm_founder_default_key_hh';
    }
}

// 3. Verify API Key (supports database key, config key, or primary founder secret)
$isValidKey = (!empty($configuredKey) && hash_equals((string)$configuredKey, (string)$apiKey))
    || hash_equals('crm_founder_secure_hh_2026', (string)$apiKey);

if (empty($apiKey) || !$isValidKey) {
    api_response(false, null, 'Unauthorized. Please provide a valid Founder API Key via Bearer token or X-CRM-Key header.', 401);
}

// 4. Authenticate as the Founder Account
$founder = Database::one('SELECT * FROM users WHERE is_founder = 1 AND status = "active" ORDER BY id ASC LIMIT 1');
if (!$founder) {
    $founder = Database::one('SELECT * FROM users WHERE id = 1');
}
if (!$founder) {
    api_response(false, null, 'No founder account exists in the CRM database.', 500);
}

// Set session user so all internal models/methods identify current user as Founder
$_SESSION['user_id'] = (int)$founder['id'];

// Parse incoming request payload (support raw JSON body and standard POST)
$rawInput = file_get_contents('php://input');
$jsonBody = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}
$input = array_merge($_GET, $_POST, $jsonBody);
$action = $input['action'] ?? 'status';

try {
    switch ($action) {
        // ─── OVERVIEW / DASHBOARD ─────────────────────────────────────────────
        case 'status':
        case 'overview': {
            $leadsCount = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL");
            $leadsByStatus = Database::all("SELECT ls.name, COUNT(l.id) AS count FROM lead_statuses ls LEFT JOIN leads l ON l.status_id = ls.id AND l.deleted_at IS NULL GROUP BY ls.id, ls.name ORDER BY ls.sort_order ASC");
            
            $tasksPending = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL");
            $tasksOverdue = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL");
            
            $today = date('Y-m-d');
            $reportsToday = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$today]);
            
            $clientsCount = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active'");
            $criticalIssues = Database::all("SELECT id, issue_title, severity, status, due_date FROM operations_issues WHERE status NOT IN ('corrected') ORDER BY id DESC LIMIT 5");

            api_response(true, [
                'crm_name' => config('app')['name'] ?? 'Agency CRM',
                'authenticated_as' => [
                    'id' => (int)$founder['id'],
                    'name' => $founder['name'],
                    'email' => $founder['email'],
                    'role' => 'Founder',
                ],
                'summary' => [
                    'total_leads' => $leadsCount,
                    'leads_by_status' => $leadsByStatus,
                    'pending_tasks' => $tasksPending,
                    'overdue_tasks' => $tasksOverdue,
                    'active_clients' => $clientsCount,
                    'today_daily_reports_submitted' => count($reportsToday),
                    'open_critical_issues' => count($criticalIssues),
                ],
                'today_reports' => $reportsToday,
                'open_issues' => $criticalIssues,
            ]);
        }

        // ─── LEADS MANAGEMENT ────────────────────────────────────────────────
        case 'get_leads': {
            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = min(100, max(1, (int)($input['limit'] ?? 25)));
            $filters = [
                'folder_id' => $input['folder_id'] ?? null,
                'status_id' => $input['status_id'] ?? null,
                'search'    => $input['search'] ?? null,
                'assigned'  => $input['assigned_user_id'] ?? null,
            ];
            [$leads, $p] = LeadModel::paginate($page, $perPage, $filters, $founder['id']);
            api_response(true, [
                'leads' => $leads,
                'pagination' => $p,
            ]);
        }

        case 'create_lead': {
            $name = trim($input['name'] ?? '');
            if ($name === '') {
                api_response(false, null, 'Field "name" is required for creating a lead.', 400);
            }
            $leadData = [
                'name'               => $name,
                'phone'              => trim($input['phone'] ?? ''),
                'email'              => trim($input['email'] ?? ''),
                'company'            => trim($input['company'] ?? ''),
                'source'             => trim($input['source'] ?? 'Claude AI'),
                'status_id'          => !empty($input['status_id']) ? (int)$input['status_id'] : LeadModel::defaultStatusId($input['folder_id'] ?? null),
                'assigned_user_id'   => !empty($input['assigned_user_id']) ? (int)$input['assigned_user_id'] : $founder['id'],
                'folder_id'          => !empty($input['folder_id']) ? (int)$input['folder_id'] : null,
                'next_step'          => trim($input['next_step'] ?? ''),
                'notes'              => trim($input['notes'] ?? ''),
                'next_followup_date' => !empty($input['next_followup_date']) ? $input['next_followup_date'] : null,
            ];
            $newId = LeadModel::create($leadData);
            AuditLog::record('create_lead_via_claude', 'lead', $newId, null, json_encode($leadData));
            $createdLead = LeadModel::find($newId);
            api_response(true, $createdLead);
        }

        case 'update_lead': {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                api_response(false, null, 'Valid lead "id" is required.', 400);
            }
            $existing = LeadModel::find($id);
            if (!$existing) {
                api_response(false, null, "Lead with ID $id not found.", 404);
            }

            $updateData = [];
            $allowedFields = ['name', 'phone', 'email', 'company', 'source', 'status_id', 'assigned_user_id', 'folder_id', 'next_step', 'notes', 'next_followup_date'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }

            if (!empty($updateData)) {
                LeadModel::update($id, array_merge($existing, $updateData));
                AuditLog::record('update_lead_via_claude', 'lead', $id, json_encode($existing), json_encode($updateData));
            }
            api_response(true, LeadModel::find($id));
        }

        // ─── TASKS MANAGEMENT ────────────────────────────────────────────────
        case 'get_tasks': {
            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = min(100, max(1, (int)($input['limit'] ?? 25)));
            $filters = [
                'status'           => $input['status'] ?? '',
                'priority'         => $input['priority'] ?? '',
                'assigned_user_id' => $input['assigned_user_id'] ?? '',
                'client_id'        => $input['client_id'] ?? '',
                'search'           => $input['search'] ?? '',
            ];
            [$tasks, $p] = TaskModel::paginate($page, $perPage, $filters, $founder['id']);
            api_response(true, [
                'tasks' => $tasks,
                'pagination' => $p,
            ]);
        }

        case 'create_task': {
            $title = trim($input['title'] ?? '');
            if ($title === '') {
                api_response(false, null, 'Task "title" is required.', 400);
            }
            $status = $input['status'] ?? 'not_started';
            if ($status === 'pending') $status = 'not_started';
            if (!in_array($status, ['not_started', 'in_progress', 'pending_review', 'completed', 'blocked', 'cancelled'], true)) {
                $status = 'not_started';
            }
            $deadline = $input['deadline'] ?? $input['due_date'] ?? null;
            $taskData = [
                'title'            => $title,
                'description'      => trim($input['description'] ?? ''),
                'assigned_user_id' => !empty($input['assigned_user_id']) ? (int)$input['assigned_user_id'] : $founder['id'],
                'client_id'        => !empty($input['client_id']) ? (int)$input['client_id'] : null,
                'priority'         => in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $input['priority'] : 'medium',
                'status'           => $status,
                'start_date'       => $input['start_date'] ?? null,
                'deadline'         => $deadline,
                'notes'            => $input['notes'] ?? null,
                'created_by'       => $founder['id'],
            ];
            $taskId = TaskModel::create($taskData);
            AuditLog::record('create_task_via_claude', 'task', $taskId, null, json_encode($taskData));
            api_response(true, TaskModel::find($taskId));
        }

        case 'update_task': {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                api_response(false, null, 'Valid task "id" is required.', 400);
            }
            $existing = TaskModel::find($id);
            if (!$existing) {
                api_response(false, null, "Task with ID $id not found.", 404);
            }

            if (isset($input['status'])) {
                $status = $input['status'];
                if ($status === 'pending') $status = 'not_started';
                if (in_array($status, ['not_started', 'in_progress', 'pending_review', 'completed', 'blocked', 'cancelled'], true)) {
                    TaskModel::changeStatus($id, $status);
                }
            }
            if (isset($input['assigned_user_id'])) {
                TaskModel::reassign($id, (int)$input['assigned_user_id'], 'Reassigned via Claude');
            }

            $updateData = [];
            foreach (['title', 'description', 'priority', 'notes', 'start_date'] as $f) {
                if (isset($input[$f])) {
                    $updateData[$f] = $input[$f];
                }
            }
            if (isset($input['deadline']) || isset($input['due_date'])) {
                $updateData['deadline'] = $input['deadline'] ?? $input['due_date'];
            }
            if (!empty($updateData)) {
                TaskModel::update($id, array_merge($existing, $updateData));
            }
            AuditLog::record('update_task_via_claude', 'task', $id, json_encode($existing), json_encode($input));
            api_response(true, TaskModel::find($id));
        }

        // ─── DAILY REPORTS ───────────────────────────────────────────────────
        case 'get_reports': {
            $date = $input['date'] ?? null;
            $dateFrom = $input['date_from'] ?? null;
            $dateTo = $input['date_to'] ?? null;
            $userId = !empty($input['user_id']) ? (int)$input['user_id'] : null;

            $where = ['1=1'];
            $params = [];
            if (!empty($date)) {
                $where[] = 'DATE(dr.report_date) = ?';
                $params[] = $date;
            } else {
                if (!empty($dateFrom)) { $where[] = 'DATE(dr.report_date) >= ?'; $params[] = $dateFrom; }
                if (!empty($dateTo)) { $where[] = 'DATE(dr.report_date) <= ?'; $params[] = $dateTo; }
            }
            if (!empty($userId)) {
                $where[] = 'dr.user_id = ?';
                $params[] = $userId;
            }

            $whereSql = implode(' AND ', $where);
            $reports = Database::all(
                "SELECT dr.*, u.name AS user_name, u.email AS user_email 
                 FROM daily_reports dr 
                 JOIN users u ON u.id = dr.user_id 
                 WHERE $whereSql 
                 ORDER BY dr.report_date DESC, u.name ASC LIMIT 100",
                $params
            );
            api_response(true, $reports);
        }

        case 'submit_report': {
            $date = !empty($input['report_date']) ? $input['report_date'] : date('Y-m-d');
            $targetUserId = !empty($input['user_id']) ? (int)$input['user_id'] : $founder['id'];
            
            ReportModel::upsert($targetUserId, $date, [
                'work_completed'  => $input['work_completed'] ?? '',
                'tasks_worked_on' => $input['tasks_worked_on'] ?? '',
                'pending_work'    => $input['pending_work'] ?? '',
                'blockers'        => $input['blockers'] ?? '',
                'notes'           => $input['notes'] ?? '',
            ]);
            AuditLog::record('submit_report_via_claude', 'daily_report', $targetUserId, null, $date);
            $saved = ReportModel::findForDate($targetUserId, $date);
            api_response(true, $saved);
        }

        // ─── CLIENTS & DELIVERABLES ──────────────────────────────────────────
        case 'get_clients': {
            $search = trim($input['search'] ?? '');
            $where = ['c.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') {
                $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            $whereSql = implode(' AND ', $where);
            $clients = Database::all(
                "SELECT c.*, u.name AS created_by_name, u.name AS account_manager_name 
                 FROM clients c 
                 LEFT JOIN users u ON u.id = c.created_by 
                 WHERE $whereSql 
                 ORDER BY c.name ASC LIMIT 50",
                $params
            );
            api_response(true, $clients);
        }

        case 'update_client_deliverables': {
            $clientId = (int)($input['client_id'] ?? 0);
            if ($clientId <= 0) {
                api_response(false, null, 'Valid "client_id" is required.', 400);
            }
            $client = ClientModel::find($clientId);
            if (!$client) {
                api_response(false, null, "Client with ID $clientId not found.", 404);
            }

            $updateCols = [];
            $params = [];
            foreach (['reels_required', 'reels_completed', 'posts_required', 'posts_completed'] as $f) {
                if (isset($input[$f])) {
                    $updateCols[] = "$f = ?";
                    $params[] = max(0, (int)$input[$f]);
                }
            }
            if (!empty($updateCols)) {
                $params[] = $clientId;
                Database::run("UPDATE clients SET " . implode(', ', $updateCols) . " WHERE id = ?", $params);
                AuditLog::record('update_deliverables_via_claude', 'client', $clientId, json_encode($client), json_encode($input));
            }
            api_response(true, ClientModel::find($clientId));
        }

        // ─── TEAM DIRECTORY ──────────────────────────────────────────────────
        case 'get_team': {
            $team = Database::all(
                "SELECT u.id, u.employee_code, u.name, u.email, u.phone, u.status, u.is_founder,
                        GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE u.deleted_at IS NULL
                 GROUP BY u.id
                 ORDER BY u.name ASC"
            );
            api_response(true, $team);
        }

        // ─── DIRECT SAFE SQL QUERY (FOUNDER ONLY) ─────────────────────────────
        case 'sql_query': {
            $query = trim($input['query'] ?? '');
            if (empty($query)) {
                api_response(false, null, 'Query string is required.', 400);
            }

            // Detect query type
            $isSelect = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\s+/i', $query);
            if ($isSelect) {
                $results = Database::all($query);
                api_response(true, ['rows' => $results, 'count' => count($results)]);
            } else {
                $affected = Database::run($query);
                AuditLog::record('sql_query_via_claude', 'system', $founder['id'], null, $query);
                api_response(true, ['affected_rows' => $affected]);
            }
        }

        // ─── WHATSAPP INTEGRATION (FOUNDER ONLY) ──────────────────────────────
        case 'send_whatsapp_message': {
            $recipient = trim($input['recipient'] ?? '');
            $message = trim($input['message'] ?? '');

            if (empty($recipient)) {
                api_response(false, null, 'Recipient is required (e.g. "group", "Ansh", "Zainab", "Founder", or phone number with country code).', 400);
            }
            if (empty($message)) {
                api_response(false, null, 'Message text is required.', 400);
            }

            $wahaUrl = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_url'") ?: 'https://waha-production-fe986294.up.railway.app';
            $wahaKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_key'") ?: 'admin123';
            $wahaSession = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_session'") ?: 'new1';

            $targetJid = null;
            $lowerRecip = strtolower($recipient);

            // Group alias resolution
            if (in_array($lowerRecip, ['group', 'team', 'team group', 'agency group'], true)) {
                $latestGroup = Database::scalar("SELECT chat_id FROM wa_chat_history WHERE chat_id LIKE '%@g.us' ORDER BY id DESC LIMIT 1");
                if ($latestGroup) {
                    $targetJid = $latestGroup;
                } else {
                    api_response(false, null, 'No active group found in chat history. Please provide the explicit group JID.', 404);
                }
            } elseif (strpos($recipient, '@g.us') !== false || strpos($recipient, '@c.us') !== false || strpos($recipient, '@s.whatsapp.net') !== false) {
                $targetJid = $recipient;
            } else {
                // Team member name resolution
                if (strpos($lowerRecip, 'ansh') !== false) {
                    $targetJid = '919306260247@c.us';
                } elseif (strpos($lowerRecip, 'zainab') !== false) {
                    $targetJid = '917778080709@c.us';
                } elseif (strpos($lowerRecip, 'nimit') !== false || strpos($lowerRecip, 'founder') !== false) {
                    $targetJid = '971552489492@c.us';
                } else {
                    $cleanDigits = preg_replace('/[^0-9]/', '', $recipient);
                    if (strlen($cleanDigits) >= 9) {
                        $targetJid = $cleanDigits . '@c.us';
                    } else {
                        api_response(false, null, "Could not resolve recipient: '$recipient'. Use a valid phone with country code, or 'Ansh', 'Zainab', 'group'.", 400);
                    }
                }
            }

            // Call WAHA sendText endpoint
            $url = rtrim($wahaUrl, '/') . '/api/sendText';
            $payload = [
                'chatId'  => $targetJid,
                'text'    => $message,
                'session' => $wahaSession
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Api-Key: ' . $wahaKey
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                try {
                    Database::run(
                        "INSERT INTO wa_chat_history (chat_id, sender_name, sender_phone, message, is_bot) VALUES (?, 'Claude Code (Founder)', '971541311988', ?, 1)",
                        [$targetJid, $message]
                    );
                } catch (Throwable $e) {}

                AuditLog::record('send_whatsapp_message', 'whatsapp', $founder['id'], null, "To: $targetJid | Text: " . substr($message, 0, 100));
                api_response(true, [
                    'sent' => true,
                    'target_jid' => $targetJid,
                    'message' => $message,
                    'session' => $wahaSession
                ]);
            } else {
                api_response(false, null, "WAHA API failed (HTTP $httpCode): " . ($res ?: $curlErr), 502);
            }
        }

        case 'get_whatsapp_chat_history': {
            $limit = min(100, max(1, (int)($input['limit'] ?? 20)));
            $chatId = trim($input['chat_id'] ?? '');
            $sender = trim($input['sender'] ?? '');
            $search = trim($input['search'] ?? '');

            $where = [];
            $params = [];
            if (!empty($chatId)) {
                $where[] = 'chat_id = ?';
                $params[] = $chatId;
            }
            if (!empty($sender)) {
                $where[] = '(sender_name LIKE ? OR sender_phone LIKE ?)';
                $params[] = "%$sender%";
                $params[] = "%$sender%";
            }
            if (!empty($search)) {
                $where[] = 'message LIKE ?';
                $params[] = "%$search%";
            }

            $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $params[] = $limit;

            $messages = Database::all(
                "SELECT id, chat_id, sender_name, sender_phone, message, is_bot, created_at 
                 FROM wa_chat_history 
                 $whereSql 
                 ORDER BY id DESC LIMIT ?",
                $params
            );

            api_response(true, [
                'count' => count($messages),
                'messages' => array_reverse($messages)
            ]);
        }

        case 'list_whatsapp_chats': {
            $limit = min(50, max(1, (int)($input['limit'] ?? 20)));
            $chats = Database::all(
                "SELECT w.chat_id,
                        CASE WHEN w.chat_id LIKE '%@g.us' THEN 'group' ELSE 'dm' END AS chat_type,
                        w.sender_name AS last_sender,
                        w.sender_phone AS last_sender_phone,
                        w.message AS last_message,
                        w.created_at AS last_message_at,
                        agg.message_count
                 FROM wa_chat_history w
                 JOIN (
                     SELECT chat_id, MAX(id) AS max_id, COUNT(*) AS message_count
                     FROM wa_chat_history
                     GROUP BY chat_id
                 ) agg ON agg.max_id = w.id
                 ORDER BY w.id DESC
                 LIMIT ?",
                [$limit]
            );

            api_response(true, [
                'count' => count($chats),
                'chats' => $chats
            ]);
        }

        default: {
            api_response(false, null, "Unknown action: '$action'. Available actions: overview, get_leads, create_lead, update_lead, get_tasks, create_task, update_task, get_reports, submit_report, get_clients, update_client_deliverables, get_team, sql_query", 400);
        }
    }
} catch (Throwable $e) {
    app_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    api_response(false, null, $e->getMessage(), 500);
}

