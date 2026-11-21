<?php
/**
 * Hekayahaus CRM - Realtime WhatsApp Webhook with Claude AI (Founder Access)
 *
 * Supports:
 * 1. Meta WhatsApp Cloud API (Official)
 * 2. Twilio WhatsApp Webhook
 *
 * Capabilities:
 * - Pair Founder's WhatsApp number securely
 * - Real-time commands: /overview, /reports, /leads, /tasks, /clients
 * - Real-time conversational Claude AI with CRM tools
 */

require_once __DIR__ . '/core/bootstrap.php';

ini_set('display_errors', '0');

function wa_log(string $msg): void {
    app_log("[WhatsApp] " . $msg);
}

// 1. Verification for Meta WhatsApp Cloud API (GET) & Browser Setup Page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = $_GET['hub_verify_token'] ?? '';
    $challenge   = $_GET['hub_challenge'] ?? '';
    $mode        = $_GET['hub_mode'] ?? '';

    // Meta Webhook Verification
    if ($mode === 'subscribe' && ($verifyToken === 'crm_founder_secure_hh_2026' || $verifyToken === 'hh_crm')) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    // Save settings if submitted via form
    if (!empty($_GET['save_settings'])) {
        if (isset($_GET['meta_phone_number_id'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_meta_phone_id', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [trim($_GET['meta_phone_number_id'])]);
        }
        if (isset($_GET['meta_access_token'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_meta_token', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [trim($_GET['meta_access_token'])]);
        }
        if (isset($_GET['founder_phone'])) {
            $cleaned = preg_replace('/[^0-9]/', '', $_GET['founder_phone']);
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_founder_phone', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$cleaned]);
        }
    }

    $savedPhoneId = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_meta_phone_id'");
    $savedToken   = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_meta_token'");
    $savedFounder = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_founder_phone'");
    $savedClaude  = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'");

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Hekayahaus CRM — WhatsApp Integration</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 650px; margin: 40px auto; padding: 24px; line-height: 1.6; background: #f8fafc; color: #0f172a; }
            .card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            h2, h3 { margin-top: 0; color: #0f172a; }
            input[type=text] { width: 100%; padding: 10px 12px; margin: 8px 0 16px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
            button { background: #16a34a; color: #fff; font-weight: 600; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
            code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-size: 13px; }
            .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .badge-ok { background: #dcfce7; color: #166534; }
            .badge-warn { background: #fef3c7; color: #92400e; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>💬 Hekayahaus CRM — WhatsApp Setup</h2>
            <p>Connect your WhatsApp account to receive real-time reports and manage CRM via Claude AI.</p>
            <p>
                <strong>Founder Phone:</strong>
                <?php if ($savedFounder): ?>
                    <span class="badge badge-ok">+<?= htmlspecialchars($savedFounder) ?> Paired</span>
                <?php else: ?>
                    <span class="badge badge-warn">Not Paired Yet</span>
                <?php endif; ?>
            </p>
            <p>
                <strong>Claude AI:</strong>
                <?php if ($savedClaude): ?>
                    <span class="badge badge-ok">Ready</span>
                <?php else: ?>
                    <span class="badge badge-warn">Needs Anthropic Key</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="card">
            <h3>Option A: Meta WhatsApp Cloud API (Free & Official)</h3>
            <p>1. In your Meta Developers App (WhatsApp > Configuration), set:</p>
            <p><strong>Callback URL:</strong> <code>https://<?= $_SERVER['HTTP_HOST'] ?>/whatsapp_webhook.php</code><br>
            <strong>Verify Token:</strong> <code>crm_founder_secure_hh_2026</code></p>
            <form method="GET">
                <input type="hidden" name="save_settings" value="1">
                <label><strong>Meta Phone Number ID:</strong></label>
                <input type="text" name="meta_phone_number_id" value="<?= htmlspecialchars($savedPhoneId ?: '') ?>" placeholder="e.g. 109876543210987">
                <label><strong>Meta System Access Token:</strong></label>
                <input type="text" name="meta_access_token" value="<?= htmlspecialchars($savedToken ?: '') ?>" placeholder="EAAG...">
                <label><strong>Founder Phone Number (with country code):</strong></label>
                <input type="text" name="founder_phone" value="<?= htmlspecialchars($savedFounder ?: '') ?>" placeholder="e.g. 919876543210">
                <button type="submit">Save Settings</button>
            </form>
        </div>

        <div class="card">
            <h3>Option B: Twilio WhatsApp (Alternative)</h3>
            <p>In your Twilio Console (WhatsApp Sandbox / Number settings), set the incoming webhook URL to:</p>
            <p><code>https://<?= $_SERVER['HTTP_HOST'] ?>/whatsapp_webhook.php</code> (HTTP POST)</p>
            <p>Then send from your WhatsApp to the Twilio number: <code>/start crm_founder_secure_hh_2026</code></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Handle Inbound WhatsApp Message (POST)
$source = 'unknown';
$from = null;
$text = '';
$metaPhoneId = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_meta_phone_id'");
$metaToken   = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_meta_token'");
$founderPhone = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_founder_phone'");
$anthropicKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'") ?: (config('app')['anthropic_api_key'] ?? '');

// Check Twilio payload
if (!empty($_POST['From']) && isset($_POST['Body'])) {
    $source = 'twilio';
    $from = preg_replace('/[^0-9]/', '', $_POST['From']);
    $text = trim($_POST['Body']);
} else {
    // Check Meta Cloud API JSON payload
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $data = json_decode($raw, true);
        $entryMsg = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if ($entryMsg) {
            $source = 'meta';
            $from = preg_replace('/[^0-9]/', '', $entryMsg['from'] ?? '');
            $text = trim($entryMsg['text']['body'] ?? '');
        }
    }
}

if (empty($from) || empty($text)) {
    echo json_encode(['ok' => true]);
    exit;
}

// Function to send reply to user
function wa_send_reply(string $source, string $to, string $replyText, ?string $metaPhoneId, ?string $metaToken): void {
    if ($source === 'twilio') {
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response><Message>" . htmlspecialchars($replyText) . "</Message></Response>";
        exit;
    }

    if ($source === 'meta' && $metaPhoneId && $metaToken) {
        $url = "https://graph.facebook.com/v19.0/{$metaPhoneId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $replyText]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $metaToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(['ok' => true]);
    exit;
}

// 3. Security Pairing
$founderSecret = 'crm_founder_secure_hh_2026';
if (stripos($text, $founderSecret) !== false) {
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_founder_phone', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$from]);
    $msg = "👑 Welcome Founder Nimit!\n\nYour WhatsApp number (+{$from}) is now securely paired with Hekayahaus CRM.\n\nYou can chat naturally or use:\n• /overview - Live stats\n• /reports - Today's reports\n• /leads - Latest leads\n• /tasks - Overdue tasks\n• /clients - Deliverables progress";
    wa_send_reply($source, $from, $msg, $metaPhoneId, $metaToken);
}

// Verify Founder Phone
if (empty($founderPhone) || $founderPhone !== $from) {
    $msg = "🔒 Access Denied\n\nThis assistant is locked to Founder Nimit Thawrani.\n\nTo pair your WhatsApp number, reply with:\n/start crm_founder_secure_hh_2026";
    wa_send_reply($source, $from, $msg, $metaPhoneId, $metaToken);
}

// Set Founder Session
$founder = Database::one('SELECT * FROM users WHERE is_founder = 1 AND status = "active" ORDER BY id ASC LIMIT 1') ?: Database::one('SELECT * FROM users WHERE id = 1');
$_SESSION['user_id'] = (int)$founder['id'];

// Handler for setting Anthropic key on the fly: `/setkey sk-ant-...`
if (preg_match('/^\/setkey\s+(\S+)/i', $text, $matches)) {
    $newAntKey = $matches[1];
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('anthropic_api_key', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$newAntKey]);
    wa_send_reply($source, $from, "✅ Anthropic API Key Saved!\n\nYou can now ask Claude anything in plain English or make changes to the CRM in real time.", $metaPhoneId, $metaToken);
}

// 4. Instant Fast Commands
$cmd = strtolower(explode(' ', $text)[0]);

switch ($cmd) {
    case '/overview':
    case '/status': {
        $totalLeads = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL");
        $todayReports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = CURDATE()");
        $pendingTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL");
        $overdueTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL");
        $activeClients = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL");

        $reply = "📊 Hekayahaus CRM — Live Overview\n\n"
               . "👥 Total Leads: {$totalLeads}\n"
               . "💼 Active Clients: {$activeClients}\n"
               . "📋 Pending Tasks: {$pendingTasks} (" . ($overdueTasks > 0 ? "⚠️ {$overdueTasks} Overdue" : "0 Overdue") . ")\n"
               . "📝 Today's Reports Submitted: " . count($todayReports) . "\n";

        if (!empty($todayReports)) {
            $reply .= "\nSubmitted Today:\n";
            foreach ($todayReports as $tr) {
                $reply .= "• " . $tr['user_name'] . "\n";
            }
        }
        wa_send_reply($source, $from, $reply, $metaPhoneId, $metaToken);
    }

    case '/reports':
    case '/today': {
        $today = date('Y-m-d');
        $reports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$today]);
        if (empty($reports)) {
            wa_send_reply($source, $from, "📝 Daily Reports for Today ({$today})\n\nNo reports have been submitted yet today.", $metaPhoneId, $metaToken);
        } else {
            $reply = "📝 Daily Reports for Today ({$today}) — " . count($reports) . " received:\n\n";
            foreach ($reports as $r) {
                $reply .= "👤 " . $r['user_name'] . "\n";
                if (!empty($r['work_completed'])) {
                    $reply .= "✅ Completed: " . substr(strip_tags($r['work_completed']), 0, 250) . "\n";
                }
                if (!empty($r['blockers']) && strtolower(trim($r['blockers'])) !== 'none' && strtolower(trim($r['blockers'])) !== 'no major blockers') {
                    $reply .= "🚨 Blockers: " . strip_tags($r['blockers']) . "\n";
                }
                $reply .= "\n";
            }
            wa_send_reply($source, $from, $reply, $metaPhoneId, $metaToken);
        }
    }

    case '/leads': {
        $leads = Database::all("SELECT name, company, phone, next_step FROM leads WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5");
        $reply = "👥 Recent Leads:\n\n";
        foreach ($leads as $l) {
            $reply .= "• " . $l['name'] . ($l['company'] ? " (" . $l['company'] . ")" : "") . "\n";
            if (!empty($l['phone'])) $reply .= "  📞 " . $l['phone'] . "\n";
            if (!empty($l['next_step'])) $reply .= "  👉 Next: " . $l['next_step'] . "\n";
        }
        wa_send_reply($source, $from, $reply, $metaPhoneId, $metaToken);
    }

    case '/tasks': {
        $tasks = Database::all("SELECT t.title, t.priority, t.deadline, u.name AS user_name FROM tasks t LEFT JOIN users u ON u.id = t.assigned_user_id WHERE t.status != 'completed' AND t.deleted_at IS NULL ORDER BY t.priority = 'urgent' DESC, t.deadline ASC LIMIT 6");
        $reply = "📋 Pending Priority Tasks:\n\n";
        foreach ($tasks as $t) {
            $reply .= "• [" . strtoupper($t['priority']) . "] " . $t['title'] . "\n";
            $reply .= "  👤 " . ($t['user_name'] ?: 'Unassigned') . ($t['deadline'] ? " | ⏰ " . substr($t['deadline'], 0, 10) : "") . "\n";
        }
        wa_send_reply($source, $from, $reply, $metaPhoneId, $metaToken);
    }

    case '/clients': {
        $clients = Database::all("SELECT name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC LIMIT 10");
        $reply = "💼 Active Client Deliverables:\n\n";
        foreach ($clients as $c) {
            $reply .= "• " . ($c['company'] ?: $c['name']) . "\n";
            $reply .= "  🎬 Reels: {$c['reels_completed']}/{$c['reels_required']} | 📸 Posts: {$c['posts_completed']}/{$c['posts_required']}\n";
        }
        wa_send_reply($source, $from, $reply, $metaPhoneId, $metaToken);
    }
}

// 5. Claude AI Integration
if (empty($anthropicKey)) {
    wa_send_reply($source, $from, "🤖 To chat naturally with Claude AI on WhatsApp, send your API key:\n/setkey sk-ant-...\n\nIn the meantime, use:\n/overview, /reports, /leads, /tasks, /clients", $metaPhoneId, $metaToken);
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

function execute_crm_tool_wa(string $name, array $args): array {
    global $founder;
    switch ($name) {
        case 'crm_get_overview':
            return [
                'total_leads' => (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL"),
                'pending_tasks' => (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL"),
                'overdue_tasks' => (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL"),
                'today_reports_count' => (int)Database::scalar("SELECT COUNT(*) FROM daily_reports WHERE DATE(report_date) = CURDATE()"),
                'active_clients' => (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL")
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
            return ['leads' => Database::all("SELECT id, name, company, phone, next_step, notes FROM leads WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 10", $params)];

        case 'crm_update_lead':
            $id = (int)($args['id'] ?? 0);
            $lead = LeadModel::find($id);
            if (!$lead) return ['error' => 'Lead not found'];
            $update = [];
            if (isset($args['status_id'])) $update['status_id'] = (int)$args['status_id'];
            if (isset($args['next_step'])) $update['next_step'] = $args['next_step'];
            if (isset($args['notes'])) $update['notes'] = $args['notes'];
            LeadModel::update($id, array_merge($lead, $update));
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
            return ['clients' => Database::all("SELECT id, name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL")];

        default:
            return ['error' => 'Unknown tool'];
    }
}

// Call Claude API
$apiUrl = "https://api.anthropic.com/v1/messages";
$payload = [
    'model' => 'claude-3-5-sonnet-latest',
    'max_tokens' => 1024,
    'system' => "You are the AI Executive Assistant for Founder Nimit Thawrani at Hekayahaus CRM. You have live tools to view leads, daily reports, tasks, and client deliverables, and can make updates on the Founder's command. Format answers concisely for WhatsApp.",
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
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$claudeRes = curl_exec($ch);
curl_close($ch);

$claudeData = json_decode($claudeRes, true);
$finalText = "";

if (isset($claudeData['content'])) {
    $hasToolUse = false;
    $toolUseBlock = null;

    foreach ($claudeData['content'] as $block) {
        if ($block['type'] === 'text') {
            $finalText .= $block['text'];
        } elseif ($block['type'] === 'tool_use') {
            $hasToolUse = true;
            $toolUseBlock = $block;
        }
    }

    if ($hasToolUse && $toolUseBlock) {
        $toolResult = execute_crm_tool_wa($toolUseBlock['name'], $toolUseBlock['input'] ?? []);

        // Follow up with tool result to get final answer from Claude
        $followUpPayload = [
            'model' => 'claude-3-5-sonnet-latest',
            'max_tokens' => 1024,
            'system' => "You are the AI Executive Assistant for Founder Nimit Thawrani at Hekayahaus CRM. Summarize the tool result concisely for WhatsApp.",
            'messages' => [
                ['role' => 'user', 'content' => $text],
                ['role' => 'assistant', 'content' => $claudeData['content']],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolUseBlock['id'],
                            'content' => json_encode($toolResult)
                        ]
                    ]
                ]
            ],
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $followUpRes = curl_exec($ch2);
        curl_close($ch2);

        $followUpData = json_decode($followUpRes, true);
        if (isset($followUpData['content'][0]['text'])) {
            $finalText = $followUpData['content'][0]['text'];
        }
    }
}

if (empty($finalText)) {
    $finalText = "⚠️ Unable to get a response from Claude. Please check your Anthropic API key or try an instant command like /overview.";
}

wa_send_reply($source, $from, $finalText, $metaPhoneId, $metaToken);
