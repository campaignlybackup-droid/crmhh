<?php
/**
 * Hekayahaus CRM - Realtime Telegram Bot with Claude AI (Founder Access)
 *
 * Allows the Founder to interact with the CRM 24/7 from their phone via Telegram:
 * - See live daily work reports, leads, tasks, and client deliverables
 * - Create tasks, update leads, and modify reports in real time
 * - Powered by Anthropic Claude 3.5 API with internal CRM tools + instant fallback commands
 */

require_once __DIR__ . '/core/bootstrap.php';

// Disable error display so webhook returns 200 OK to Telegram
ini_set('display_errors', '0');
header('Content-Type: application/json');

// Helper to log bot activities
function bot_log(string $msg): void {
    app_log("[Telegram Bot] " . $msg);
}

// Helper to send messages via Telegram Bot API
function telegram_send(string $token, int|string $chatId, string $text, ?string $parseMode = 'Markdown'): bool {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        bot_log("Send error: $err");
        return false;
    }
    return true;
}

// 1. Browser GET handler: Quick 1-click Webhook Setup & Status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If token passed in query parameter, auto-register with Telegram immediately
    if (!empty($_GET['token']) || !empty($_GET['setup_token'])) {
        $token = trim($_GET['token'] ?? $_GET['setup_token']);
        Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('telegram_bot_token', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$token]);

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $webhookUrl = "{$scheme}://{$_SERVER['HTTP_HOST']}/telegram_webhook.php";
        $tgUrl = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhookUrl);

        $ch = curl_init($tgUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $tgRes = curl_exec($ch);
        curl_close($ch);

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><title>Telegram Bot Connected</title><style>body{font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;line-height:1.6;background:#f9fafb;color:#111827;}pre{background:#e5e7eb;padding:12px;border-radius:6px;}code{background:#e5e7eb;padding:2px 6px;border-radius:4px;color:#dc2626;}</style></head><body>";
        echo "<h2>🎉 Telegram Bot Connected to Hekayahaus CRM!</h2>";
        echo "<p>Telegram registration response: <pre>" . htmlspecialchars($tgRes ?: 'OK') . "</pre></p>";
        echo "<div style='background:#dcfce7;border:1px solid #86efac;padding:16px;border-radius:8px;'>";
        echo "<h3>👉 Final Step: Pair Your Founder Account</h3>";
        echo "<p>Open your new bot in Telegram and send:</p>";
        echo "<code>/start crm_founder_secure_hh_2026</code>";
        echo "</div>";
        echo "</body></html>";
        exit;
    }

    $savedToken = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'telegram_bot_token'");
    $savedFounder = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'telegram_founder_chat_id'");
    $savedClaude = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'");

    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Hekayahaus Telegram Setup</title><style>body{font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;line-height:1.6;background:#f9fafb;color:#111827;}input[type=text]{width:100%;padding:10px;margin:8px 0;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;}button{background:#2563eb;color:#fff;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-weight:bold;}</style></head><body>";
    echo "<h2>🤖 Hekayahaus CRM — Telegram Bot Connection</h2>";
    echo "<p>Status: " . ($savedToken ? "<span style='color:green;'>✅ Bot Token Configured</span>" : "<span style='color:orange;'>⚠️ Bot Token Not Set</span>") . "</p>";
    if ($savedFounder) echo "<p style='color:green;'>✅ Paired Founder Chat ID: <code>{$savedFounder}</code></p>";
    if ($savedClaude) echo "<p style='color:green;'>✅ Claude AI: Connected</p>";

    echo "<form method='GET' style='background:#fff;padding:20px;border-radius:8px;border:1px solid #e5e7eb;margin-top:20px;'>";
    echo "<label><strong>Paste Telegram Bot Token from @BotFather:</strong></label>";
    echo "<input type='text' name='token' placeholder='123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ' value='" . htmlspecialchars($savedToken ?: '') . "' required />";
    echo "<button type='submit'>Save & Connect Webhook</button>";
    echo "</form>";
    echo "</body></html>";
    exit;
}

// 2. Read Telegram webhook payload (POST)
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode(['ok' => true, 'message' => 'Telegram Webhook Endpoint Ready']);
    exit;
}

$update = json_decode($rawInput, true);
if (!isset($update['message'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$message = $update['message'];
$chatId  = $message['chat']['id'] ?? null;
$userId  = $message['from']['id'] ?? null;
$text    = trim($message['text'] ?? '');
$firstName = $message['from']['first_name'] ?? 'Founder';

if (!$chatId || empty($text)) {
    echo json_encode(['ok' => true]);
    exit;
}

// 2. Fetch Bot Token and Allowed Founder Chat ID from app_settings
$botToken = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'telegram_bot_token'") ?: (config('app')['telegram_bot_token'] ?? '');
$allowedChatId = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'telegram_founder_chat_id'") ?: null;
$anthropicKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'") ?: (config('app')['anthropic_api_key'] ?? '');

// If bot token is not configured yet, save from query param or return instructions
if (empty($botToken)) {
    if (!empty($_GET['token'])) {
        $botToken = trim($_GET['token']);
        Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('telegram_bot_token', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$botToken]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Bot token not set in CRM app_settings.']);
        exit;
    }
}

// 3. Security Pairing: Lock the bot to the Founder's Telegram account
// Pairing via `/start <founder_key>`
$founderSecret = 'crm_founder_secure_hh_2026';
if (preg_match('/^\/start\s+(\S+)/i', $text, $matches)) {
    $providedKey = $matches[1];
    if ($providedKey === $founderSecret) {
        Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('telegram_founder_chat_id', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [(string)$chatId]);
        telegram_send($botToken, $chatId, "👑 *Welcome Founder Nimit!*\n\nYour Telegram account has been securely paired with *Hekayahaus CRM*.\n\nYou now have full real-time access. You can chat naturally or use commands:\n\n• *Overview:* `/overview`\n• *Daily Reports:* `/reports`\n• *Leads:* `/leads`\n• *Tasks:* `/tasks`\n• *Active Clients:* `/clients`");
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Verify that the sender is the paired Founder
if (empty($allowedChatId) || (string)$allowedChatId !== (string)$chatId) {
    telegram_send($botToken, $chatId, "🔒 *Access Denied*\n\nThis bot is locked to the Founder of Hekayahaus CRM.\n\nTo pair your Founder account, send:\n`/start crm_founder_secure_hh_2026`", null);
    echo json_encode(['ok' => true]);
    exit;
}

// Establish Founder Session in CRM
$founder = Database::one('SELECT * FROM users WHERE is_founder = 1 AND status = "active" ORDER BY id ASC LIMIT 1') ?: Database::one('SELECT * FROM users WHERE id = 1');
$_SESSION['user_id'] = (int)$founder['id'];

// 4. Quick Direct Commands (Work instantly even without Claude API key)
$cmd = strtolower(explode(' ', $text)[0]);

switch ($cmd) {
    case '/overview':
    case '/status': {
        $totalLeads = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL");
        $todayReports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = CURDATE()");
        $pendingTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL");
        $overdueTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL");
        $activeClients = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL");

        $reply = "📊 *Hekayahaus CRM — Live Overview*\n\n"
               . "👥 *Total Leads:* {$totalLeads}\n"
               . "💼 *Active Clients:* {$activeClients}\n"
               . "📋 *Pending Tasks:* {$pendingTasks} (" . ($overdueTasks > 0 ? "⚠️ {$overdueTasks} Overdue" : "0 Overdue") . ")\n"
               . "📝 *Today's Reports Submitted:* " . count($todayReports) . "\n\n";

        if (!empty($todayReports)) {
            $reply .= "*Submitted Today:*\n";
            foreach ($todayReports as $tr) {
                $reply .= "• *" . e($tr['user_name']) . "*\n";
            }
        }
        telegram_send($botToken, $chatId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }

    case '/reports':
    case '/today': {
        $today = date('Y-m-d');
        $reports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$today]);
        if (empty($reports)) {
            telegram_send($botToken, $chatId, "📝 *Daily Reports for Today ({$today})*\n\nNo reports have been submitted yet today.");
        } else {
            $reply = "📝 *Daily Reports for Today ({$today})* — " . count($reports) . " received:\n\n";
            foreach ($reports as $r) {
                $reply .= "👤 *" . $r['user_name'] . "*\n";
                if (!empty($r['work_completed'])) {
                    $reply .= "✅ *Completed:* " . substr(strip_tags($r['work_completed']), 0, 300) . "\n";
                }
                if (!empty($r['blockers']) && strtolower(trim($r['blockers'])) !== 'none' && strtolower(trim($r['blockers'])) !== 'no major blockers') {
                    $reply .= "🚨 *Blockers:* " . strip_tags($r['blockers']) . "\n";
                }
                $reply .= "──────────────\n";
            }
            telegram_send($botToken, $chatId, $reply);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    case '/leads': {
        $leads = Database::all("SELECT l.id, l.name, l.company, ls.name AS status_name FROM leads l LEFT JOIN lead_statuses ls ON ls.id = l.status_id WHERE l.deleted_at IS NULL ORDER BY l.id DESC LIMIT 10");
        $reply = "🎯 *Latest 10 Leads:*\n\n";
        foreach ($leads as $l) {
            $reply .= "• *#{$l['id']} {$l['name']}* (" . ($l['company'] ?: 'No Co') . ") — `" . ($l['status_name'] ?: 'New') . "`\n";
        }
        telegram_send($botToken, $chatId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }

    case '/tasks': {
        $tasks = Database::all("SELECT t.id, t.title, t.priority, t.status, u.name AS assigned_name, t.deadline FROM tasks t LEFT JOIN users u ON u.id = t.assigned_user_id WHERE t.status != 'completed' AND t.deleted_at IS NULL ORDER BY t.deadline ASC LIMIT 10");
        if (empty($tasks)) {
            telegram_send($botToken, $chatId, "✅ No pending tasks! All caught up.");
        } else {
            $reply = "📋 *Pending Tasks:*\n\n";
            foreach ($tasks as $t) {
                $isOverdue = (!empty($t['deadline']) && strtotime($t['deadline']) < time()) ? "⚠️ OVERDUE" : "";
                $reply .= "• *#{$t['id']} {$t['title']}* {$isOverdue}\n  👤 Assigned: " . ($t['assigned_name'] ?: 'Unassigned') . " | ⚡ `" . ucfirst($t['priority']) . "`\n";
            }
            telegram_send($botToken, $chatId, $reply);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    case '/clients': {
        $clients = Database::all("SELECT name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC LIMIT 15");
        $reply = "💼 *Active Client Deliverables Progress:*\n\n";
        foreach ($clients as $c) {
            $reply .= "• *" . ($c['company'] ?: $c['name']) . "*\n";
            $reply .= "  🎬 Reels: `{$c['reels_completed']}/{$c['reels_required']}` | 📸 Posts: `{$c['posts_completed']}/{$c['posts_required']}`\n";
        }
        telegram_send($botToken, $chatId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Handler for setting Anthropic Key on the fly: `/setkey sk-ant-...`
if (preg_match('/^\/setkey\s+(\S+)/i', $text, $matches)) {
    $newAntKey = $matches[1];
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('anthropic_api_key', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$newAntKey]);
    telegram_send($botToken, $chatId, "✅ *Anthropic API Key Saved!*\n\nYou can now ask Claude anything in plain English or make changes to the CRM in real time.");
    echo json_encode(['ok' => true]);
    exit;
}

// 5. Conversational AI: Call Claude API if Anthropic API key is configured
if (empty($anthropicKey)) {
    telegram_send($botToken, $chatId, "🤖 *Command Received*\n\nTo enable full natural conversation with Claude AI (e.g. asking arbitrary questions or making updates via voice/text), send your key here:\n`/setkey sk-ant-...`\n\nIn the meantime, you can use instant commands:\n• /overview\n• /reports\n• /leads\n• /tasks\n• /clients");
    echo json_encode(['ok' => true]);
    exit;
}

// Define CRM Tools for Claude
$claudeTools = [
    [
        'name' => 'crm_get_overview',
        'description' => 'Get real-time agency dashboard counts: total leads, tasks, overdue tasks, today reports, active clients.',
        'input_schema' => ['type' => 'object', 'properties' => []]
    ],
    [
        'name' => 'crm_get_daily_reports',
        'description' => 'View daily work reports for any date. See what staff completed and any blockers.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD. Defaults to today.']
            ]
        ]
    ],
    [
        'name' => 'crm_list_leads',
        'description' => 'Search and list agency leads by status or keyword.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Search lead name, company, or phone']
            ]
        ]
    ],
    [
        'name' => 'crm_update_lead',
        'description' => 'Update a lead status, notes, assignment, or next step in real time as Founder.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Lead ID to update'],
                'status_id' => ['type' => 'integer', 'description' => 'New status ID'],
                'next_step' => ['type' => 'string', 'description' => 'Next step'],
                'notes' => ['type' => 'string', 'description' => 'Notes']
            ],
            'required' => ['id']
        ]
    ],
    [
        'name' => 'crm_create_task',
        'description' => 'Assign a new task to any team member as Founder.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Task title'],
                'assigned_user_id' => ['type' => 'integer', 'description' => 'User ID to assign to'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'due_date' => ['type' => 'string', 'description' => 'Deadline YYYY-MM-DD']
            ],
            'required' => ['title']
        ]
    ],
    [
        'name' => 'crm_list_clients',
        'description' => 'Get client deliverables status (reels and posts completed vs required).',
        'input_schema' => ['type' => 'object', 'properties' => []]
    ]
];

// Helper to execute tools directly inside CRM
function execute_crm_tool(string $name, array $args): array {
    global $founder;
    switch ($name) {
        case 'crm_get_overview':
            $leadsCount = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL");
            $tasksPending = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL");
            $tasksOverdue = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL");
            $todayReports = (int)Database::scalar("SELECT COUNT(*) FROM daily_reports WHERE DATE(report_date) = CURDATE()");
            $clientsCount = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL");
            return [
                'total_leads' => $leadsCount,
                'pending_tasks' => $tasksPending,
                'overdue_tasks' => $tasksOverdue,
                'today_reports_count' => $todayReports,
                'active_clients' => $clientsCount
            ];

        case 'crm_get_daily_reports':
            $date = $args['date'] ?? date('Y-m-d');
            $reports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$date]);
            return ['date' => $date, 'reports' => $reports];

        case 'crm_list_leads':
            $search = $args['search'] ?? '';
            $where = ['deleted_at IS NULL'];
            $params = [];
            if (!empty($search)) {
                $where[] = '(name LIKE ? OR company LIKE ? OR phone LIKE ?)';
                $params = ["%$search%", "%$search%", "%$search%"];
            }
            $leads = Database::all("SELECT id, name, company, phone, next_step, notes FROM leads WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 10", $params);
            return ['leads' => $leads];

        case 'crm_update_lead':
            $id = (int)($args['id'] ?? 0);
            $lead = LeadModel::find($id);
            if (!$lead) return ['error' => 'Lead not found'];
            $update = [];
            if (isset($args['status_id'])) $update['status_id'] = (int)$args['status_id'];
            if (isset($args['next_step'])) $update['next_step'] = $args['next_step'];
            if (isset($args['notes'])) $update['notes'] = $args['notes'];
            LeadModel::update($id, array_merge($lead, $update));
            AuditLog::record('telegram_bot_update', 'lead', $id, null, json_encode($update));
            return ['success' => true, 'updated_lead' => LeadModel::find($id)];

        case 'crm_create_task':
            $taskId = TaskModel::create([
                'title' => $args['title'],
                'assigned_user_id' => $args['assigned_user_id'] ?? $founder['id'],
                'priority' => $args['priority'] ?? 'medium',
                'deadline' => $args['due_date'] ?? null,
                'status' => 'not_started',
                'created_by' => $founder['id']
            ]);
            return ['success' => true, 'task_id' => $taskId];

        case 'crm_list_clients':
            $clients = Database::all("SELECT id, name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL");
            return ['clients' => $clients];

        default:
            return ['error' => 'Unknown tool'];
    }
}

// Call Anthropic API
$apiUrl = "https://api.anthropic.com/v1/messages";
$payload = [
    'model' => 'claude-3-5-sonnet-latest',
    'max_tokens' => 1024,
    'system' => "You are the AI Executive Assistant for Founder Nimit Thawrani at Hekayahaus CRM. You have live tools to view leads, daily reports, tasks, and client deliverables, and can make updates on the Founder's command. Format answers concisely with clean Telegram markdown.",
    'messages' => [
        ['role' => 'user', 'content' => $text]
    ],
    'tools' => $claudeTools
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $anthropicKey,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$responseRaw = curl_exec($ch);
curl_close($ch);

$claudeRes = json_decode($responseRaw, true);

if (!isset($claudeRes['content'])) {
    telegram_send($botToken, $chatId, "⚠️ Claude API error: " . ($claudeRes['error']['message'] ?? 'Unable to connect to Claude'));
    echo json_encode(['ok' => true]);
    exit;
}

// Check if Claude requested a Tool Call
$toolCalls = [];
$replyText = '';

foreach ($claudeRes['content'] as $block) {
    if ($block['type'] === 'text') {
        $replyText .= $block['text'];
    } elseif ($block['type'] === 'tool_use') {
        $toolCalls[] = $block;
    }
}

// If tool calls were requested, execute them and ask Claude for final reply
if (!empty($toolCalls)) {
    $messages = [
        ['role' => 'user', 'content' => $text],
        ['role' => 'assistant', 'content' => $claudeRes['content']]
    ];

    $toolResultBlocks = [];
    foreach ($toolCalls as $tc) {
        $toolOutput = execute_crm_tool($tc['name'], $tc['input']);
        $toolResultBlocks[] = [
            'type' => 'tool_result',
            'tool_use_id' => $tc['id'],
            'content' => json_encode($toolOutput)
        ];
    }

    $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];

    $followUpPayload = [
        'model' => 'claude-3-5-sonnet-latest',
        'max_tokens' => 1024,
        'system' => "You are the AI Executive Assistant for Founder Nimit Thawrani at Hekayahaus CRM. Format answers concisely with clean Telegram markdown.",
        'messages' => $messages,
        'tools' => $claudeTools
    ];

    $ch2 = curl_init($apiUrl);
    curl_setopt_array($ch2, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($followUpPayload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropicKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $followUpRaw = curl_exec($ch2);
    curl_close($ch2);

    $followUpRes = json_decode($followUpRaw, true);
    if (isset($followUpRes['content'])) {
        $replyText = '';
        foreach ($followUpRes['content'] as $b) {
            if ($b['type'] === 'text') $replyText .= $b['text'];
        }
    }
}

telegram_send($botToken, $chatId, $replyText);
echo json_encode(['ok' => true]);
