<?php
/**
 * Hekayahaus CRM - Realtime WhatsApp Webhook & Claude AI Autonomous Brain
 *
 * Capabilities:
 * 1. 1-on-1 Founder Chat: Live CRM queries, task assignment, proactive team follow-ups.
 * 2. Team WhatsApp Groups: Automatically reads staff daily updates, logs daily reports,
 *    updates deliverables (reels/posts), and acknowledges the team.
 * 3. Autonomous Follow-ups: Sends polite, human 1-on-1 messages to staff members on Founder request.
 *
 * Supports:
 * - Evolution API (Free Open-Source Gateway for Groups & DMs)
 * - Custom Baileys / Node.js Bridge
 * - Meta WhatsApp Cloud API
 * - Twilio WhatsApp
 */

require_once __DIR__ . '/core/bootstrap.php';

ini_set('display_errors', '0');

function wa_log(string $msg): void {
    app_log("[WhatsApp] " . $msg);
}

// ─── 1. WEB DASHBOARD & CONFIGURATION (GET) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = $_GET['hub_verify_token'] ?? '';
    $challenge   = $_GET['hub_challenge'] ?? '';
    $mode        = $_GET['hub_mode'] ?? '';

    // Meta Webhook Verification handshake
    if ($mode === 'subscribe' && ($verifyToken === 'crm_founder_secure_hh_2026' || $verifyToken === 'hh_crm')) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    // Save settings if submitted
    if (!empty($_GET['save_settings'])) {
        if (isset($_GET['founder_phone'])) {
            $cleaned = preg_replace('/[^0-9]/', '', $_GET['founder_phone']);
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_founder_phone', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$cleaned]);
        }
        if (isset($_GET['evolution_url'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_evolution_url', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [rtrim(trim($_GET['evolution_url']), '/')]);
        }
        if (isset($_GET['evolution_key'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_evolution_key', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [trim($_GET['evolution_key'])]);
        }
        if (isset($_GET['evolution_instance'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_evolution_instance', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [trim($_GET['evolution_instance'])]);
        }
        if (isset($_GET['anthropic_api_key'])) {
            Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('anthropic_api_key', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [trim($_GET['anthropic_api_key'])]);
        }
    }

    $savedFounder   = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_founder_phone'");
    $savedEvoUrl    = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_url'");
    $savedEvoKey    = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_key'");
    $savedEvoInst   = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_instance'") ?: 'hekayahaus';
    $savedClaude    = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'");

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Hekayahaus CRM — WhatsApp AI Integration</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 680px; margin: 30px auto; padding: 20px; line-height: 1.6; background: #f8fafc; color: #0f172a; }
            .card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            h2, h3 { margin-top: 0; color: #0f172a; }
            input[type=text], input[type=password] { width: 100%; padding: 10px 12px; margin: 6px 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
            button { background: #2563eb; color: #fff; font-weight: 600; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
            code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-size: 13px; }
            .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .badge-ok { background: #dcfce7; color: #166534; }
            .badge-warn { background: #fef3c7; color: #92400e; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>💬 Hekayahaus CRM — WhatsApp AI Brain</h2>
            <p>Connect WhatsApp to chat 1-on-1 with Claude, listen to team group reports, and take autonomous follow-ups.</p>
            <p><strong>Founder Phone:</strong> <?= $savedFounder ? "<span class='badge badge-ok'>+$savedFounder Paired</span>" : "<span class='badge badge-warn'>Not Paired</span>" ?></p>
            <p><strong>Claude 3.5 AI:</strong> <?= $savedClaude ? "<span class='badge badge-ok'>Connected</span>" : "<span class='badge badge-warn'>Needs Anthropic API Key</span>" ?></p>
            <p><strong>Gateway Status:</strong> <?= $savedEvoUrl ? "<span class='badge badge-ok'>Evolution API Configured</span>" : "<span class='badge badge-warn'>Pending Gateway Setup</span>" ?></p>
        </div>

        <div class="card">
            <h3>⚙️ Connect Evolution API (Free Open-Source Gateway)</h3>
            <p>Set your Evolution API webhook URL to:<br><code>https://<?= $_SERVER['HTTP_HOST'] ?>/whatsapp_webhook.php</code></p>
            <form method="GET">
                <input type="hidden" name="save_settings" value="1">
                <label><strong>Evolution API Server URL:</strong></label>
                <input type="text" name="evolution_url" value="<?= htmlspecialchars($savedEvoUrl ?: '') ?>" placeholder="e.g. https://my-evo.onrender.com or http://localhost:8080">
                <label><strong>Evolution API Global Key (Password):</strong></label>
                <input type="password" name="evolution_key" value="<?= htmlspecialchars($savedEvoKey ?: '') ?>" placeholder="Your API Key">
                <label><strong>Instance Name:</strong></label>
                <input type="text" name="evolution_instance" value="<?= htmlspecialchars($savedEvoInst ?: 'hekayahaus') ?>" placeholder="hekayahaus">
                <label><strong>Founder WhatsApp Phone (with Country Code):</strong></label>
                <input type="text" name="founder_phone" value="<?= htmlspecialchars($savedFounder ?: '') ?>" placeholder="e.g. 919876543210">
                <label><strong>Anthropic API Key (Claude AI):</strong></label>
                <input type="password" name="anthropic_api_key" value="<?= htmlspecialchars($savedClaude ?: '') ?>" placeholder="sk-ant-api03-...">
                <button type="submit">Save Settings</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ─── 2. PARSE INCOMING MESSAGE (POST) ─────────────────────────────────────────
$source      = 'unknown';
$from        = null;        // Phone number of individual sender
$text        = '';          // Message body
$isGroup     = false;
$groupId     = null;        // Group JID if sent inside a group
$replyToJid  = null;        // Where to send reply (group JID or individual JID)
$senderName  = 'Team Member';
$rawParticipant = null;
$msgTimestamp = 0;

$evoUrl      = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_url'");
$evoKey      = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_key'");
$evoInstance = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_evolution_instance'") ?: 'hekayahaus';
$wahaUrl     = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_url'");
$founderPhone= Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_founder_phone'");
$anthropicKey= Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'anthropic_api_key'") ?: (config('app')['anthropic_api_key'] ?? '');

$rawInput = file_get_contents('php://input');

// A. Check Evolution API / Baileys / WAHA JSON payload
if (!empty($rawInput)) {
    $data = json_decode($rawInput, true);
    $event = $data['event'] ?? '';

    // 1. WAHA (WhatsApp HTTP API) Payload
    // Strictly filter: ONLY handle the primary 'message' event. Discard 'message.ack', 'message.any', etc.
    if ($event === 'message' && isset($data['payload'])) {
        $source = 'waha';
        $wahaSession = $data['session'] ?? 'new1';
        Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_waha_session', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$wahaSession]);

        $payload = $data['payload'] ?? [];
        if (!empty($payload['fromMe'])) {
            $selfBody = trim($payload['body'] ?? '');
            $selfTo = $payload['to'] ?? ($payload['from'] ?? '');
            if ($selfBody !== '' && $selfTo !== '') {
                try {
                    $recentDup = Database::scalar(
                        "SELECT id FROM wa_chat_history WHERE chat_id = ? AND message = ? AND is_bot = 1 AND created_at > NOW() - INTERVAL 15 SECOND LIMIT 1",
                        [$selfTo, $selfBody]
                    );
                    if (!$recentDup) {
                        Database::run(
                            "INSERT INTO wa_chat_history (chat_id, sender_name, sender_phone, message, is_bot) VALUES (?, 'Me (WhatsApp)', '971541311988', ?, 1)",
                            [$selfTo, $selfBody]
                        );
                    }
                } catch (Throwable $e) {}
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        $fromJid = $payload['from'] ?? '';
        $rawParticipant = $payload['participant'] ?? ($payload['author'] ?? ($payload['_data']['participant'] ?? $fromJid));
        $cleanParticipant = preg_replace('/[^0-9]/', '', explode('@', $rawParticipant)[0]);

        // Ignore if sent by the bot's own number or LID
        if ($cleanParticipant === '971541311988' || $cleanParticipant === '121131717656587' || strpos($rawParticipant, '971541311988') !== false || strpos($rawParticipant, '121131717656587') !== false) {
            echo json_encode(['ok' => true]);
            exit;
        }

        $replyToJid = $fromJid;
        $isGroup = (strpos($fromJid, '@g.us') !== false);
        if ($isGroup) {
            $groupId = $fromJid;
            $from = $cleanParticipant;
        } else {
            $from = preg_replace('/[^0-9]/', '', explode('@', $fromJid)[0]);
        }

        $senderName = $payload['_data']['notifyName'] ?? ($payload['pushName'] ?? ($payload['_data']['pushname'] ?? 'Team Member'));
        $text = trim($payload['body'] ?? '');
        $msgTimestamp = (int)($payload['timestamp'] ?? 0);
    }
    // 2. Evolution API Payload
    elseif (strpos($event, 'messages.upsert') !== false && isset($data['data'])) {
        $source = 'evolution';
        $msgData = $data['data'] ?? [];
        $key = $msgData['key'] ?? [];

        if (!empty($key['fromMe'])) {
            echo json_encode(['ok' => true]);
            exit;
        }

        $remoteJid = $key['remoteJid'] ?? '';
        $replyToJid = $remoteJid;
        $isGroup = (strpos($remoteJid, '@g.us') !== false);

        if ($isGroup) {
            $groupId = $remoteJid;
            $rawParticipant = $key['participant'] ?? ($msgData['participant'] ?? '');
            $from = preg_replace('/[^0-9]/', '', explode('@', $rawParticipant)[0]);
        } else {
            $from = preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
        }

        $senderName = $msgData['pushName'] ?? 'Team Member';
        $messageObj = $msgData['message'] ?? [];
        $text = trim($messageObj['conversation'] ?? ($messageObj['extendedTextMessage']['text'] ?? ''));
        $msgTimestamp = (int)($msgData['messageTimestamp'] ?? 0);
    }
    // B. Check Custom Bridge Payload
    elseif ($event === 'whatsapp_message') {
        $source = 'bridge';
        $isGroup = !empty($data['is_group']);
        $from = preg_replace('/[^0-9]/', '', $data['sender_phone'] ?? $data['from'] ?? '');
        $replyToJid = $data['reply_to'] ?? ($from . '@s.whatsapp.net');
        $senderName = $data['sender_name'] ?? 'Team Member';
        $text = trim($data['text'] ?? '');
    }
    // C. Meta Cloud API Payload
    elseif (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        $source = 'meta';
        $entryMsg = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $from = preg_replace('/[^0-9]/', '', $entryMsg['from'] ?? '');
        $replyToJid = $from;
        $text = trim($entryMsg['text']['body'] ?? '');
        $msgTimestamp = (int)($entryMsg['timestamp'] ?? 0);
    }
}
// D. Twilio Payload
elseif (!empty($_POST['From']) && isset($_POST['Body'])) {
    $source = 'twilio';
    $from = preg_replace('/[^0-9]/', '', $_POST['From']);
    $replyToJid = $from;
    $text = trim($_POST['Body']);
}

if (empty($from) || empty($text)) {
    echo json_encode(['ok' => true]);
    exit;
}

// ─── CHAT HISTORY HELPERS ─────────────────────────────────────────────────────
function wa_save_chat_history(string $chatId, string $senderName, ?string $senderPhone, string $message, bool $isBot): void {
    if (empty($message) || stripos($message, '[NO_REPLY]') !== false) return;
    try {
        Database::run(
            "INSERT INTO wa_chat_history (chat_id, sender_name, sender_phone, message, is_bot) VALUES (?, ?, ?, ?, ?)",
            [$chatId, $senderName, $senderPhone, $message, $isBot ? 1 : 0]
        );
        if (random_int(1, 100) === 1) {
            Database::run("DELETE FROM wa_chat_history WHERE created_at < NOW() - INTERVAL 7 DAY");
        }
    } catch (Throwable $e) {}
}

function wa_get_chat_history(string $chatId, int $limit = 8): array {
    try {
        $rows = Database::all(
            "SELECT sender_name, message, is_bot FROM wa_chat_history WHERE chat_id = ? ORDER BY id DESC LIMIT ?",
            [$chatId, $limit]
        );
        return array_reverse($rows ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

// ─── HISTORICAL MESSAGE FILTER ────────────────────────────────────────────────
// If a message was sent over 90 seconds ago (e.g. historical messages synced on reconnect),
// save it to conversation context for memory but DO NOT generate a reply.
if ($msgTimestamp > 0 && $msgTimestamp < (time() - 90)) {
    wa_save_chat_history($replyToJid, $senderName, $from, $text, false);
    echo json_encode(['ok' => true, 'ignored' => 'historical_context_only']);
    exit;
}

// ─── DEDUPLICATION & CONCURRENCY LOCK ─────────────────────────────────────────
$rawMsgId = $payload['id'] ?? ($payload['_data']['id'] ?? ($msgData['key']['id'] ?? ($entryMsg['id'] ?? null)));
if (is_array($rawMsgId)) {
    $msgId = $rawMsgId['_serialized'] ?? ($rawMsgId['id'] ?? json_encode($rawMsgId));
} else {
    $msgId = (string)($rawMsgId ?? '');
}
if (empty($msgId)) {
    $msgId = 'hash_' . md5(($from ?? '') . '_' . ($groupId ?? 'dm') . '_' . $msgTimestamp . '_' . substr($text, 0, 50));
}

try {
    Database::run("CREATE TABLE IF NOT EXISTS wa_processed_messages (
        msg_id VARCHAR(191) PRIMARY KEY,
        status VARCHAR(20) DEFAULT 'processing',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Database::run() returns affected row count (int), NOT a PDOStatement.
    $affected = Database::run("INSERT IGNORE INTO wa_processed_messages (msg_id, status) VALUES (?, 'processing')", [$msgId]);
    if ($affected === 0) {
        wa_log("Duplicate message ignored: {$msgId}");
        echo json_encode(['ok' => true, 'ignored' => 'duplicate_in_flight']);
        exit;
    }

    // Secondary 10-second content+sender+chat lock in case WAHA sends duplicate webhooks with different IDs
    $windowBucket = (int)floor(($msgTimestamp > 0 ? $msgTimestamp : time()) / 10);
    $windowHash = 'win_' . md5(($replyToJid ?? '') . '|' . ($from ?? '') . '|' . $text . '|' . $windowBucket);
    $winAffected = Database::run("INSERT IGNORE INTO wa_processed_messages (msg_id, status) VALUES (?, 'processing')", [$windowHash]);
    if ($winAffected === 0) {
        wa_log("Duplicate window message ignored: {$windowHash}");
        echo json_encode(['ok' => true, 'ignored' => 'duplicate_window']);
        exit;
    }
    if (random_int(1, 50) === 1) {
        Database::run("DELETE FROM wa_processed_messages WHERE created_at < NOW() - INTERVAL 2 HOUR");
    }
} catch (Throwable $e) {}

// Acknowledge webhook immediately to prevent WAHA / proxy timeout retries
if (function_exists('fastcgi_finish_request')) {
    echo json_encode(['ok' => true]);
    fastcgi_finish_request();
}

// Helper to send WhatsApp messages back
function wa_send_message(string $source, string $targetJid, string $replyText, ?string $evoUrl, ?string $evoKey, ?string $evoInstance, array $extraMentions = []): void {
    if (trim($replyText) === '' || stripos($replyText, '[NO_REPLY]') !== false) {
        return;
    }

    $wahaUrl = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_url'");
    $wahaKey = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_key'") ?: 'admin123';
    $wahaSession = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_waha_session'") ?: 'new1';

    // WAHA reply
    if ($source === 'waha' || (!empty($wahaUrl) && $source !== 'evolution')) {
        $url = rtrim($wahaUrl, '/') . "/api/sendText";
        $payload = [
            'chatId'  => $targetJid,
            'text'    => $replyText,
            'session' => $wahaSession
        ];

        // Collect mentions
        $mentions = $extraMentions;
        preg_match_all('/@([0-9]{8,15})/', $replyText, $m);
        if (!empty($m[1])) {
            foreach ($m[1] as $ph) {
                $mentions[] = $ph . '@c.us';
            }
        }
        if (!empty($mentions)) {
            $payload['mentions'] = array_values(array_unique($mentions));
        }

        $headers = ['Content-Type: application/json'];
        if (!empty($wahaKey)) {
            $headers[] = 'X-Api-Key: ' . $wahaKey;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
        return;
    }

    if ($source === 'evolution' || !empty($evoUrl)) {
        if ($evoUrl && $evoKey) {
            $url = rtrim($evoUrl, '/') . "/message/sendText/{$evoInstance}";
            $payload = [
                'number' => $targetJid,
                'text' => $replyText,
                'delay' => 1200,
                'linkPreview' => false,
            ];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'apikey: ' . $evoKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        return;
    }

    if ($source === 'twilio') {
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response><Message>" . htmlspecialchars($replyText) . "</Message></Response>";
        exit;
    }
}

// ─── 3. USER & FOUNDER IDENTIFICATION ────────────────────────────────────────
$cleanFrom = preg_replace('/[^0-9]/', '', (string)$from);
$participantLid = null;
if (!empty($rawParticipant)) {
    $participantLid = preg_replace('/[^0-9]/', '', explode('@', $rawParticipant)[0]);
}

// Known Team Members mapping (Unconditional & Instant Resolution)
// Maps WhatsApp LIDs and phone numbers to real CRM user profiles
$knownTeamMap = [
    // Ansh (ID: 2)
    '256559921770569' => ['name' => 'Ansh', 'phone' => '919306260247', 'user_id' => 2, 'role' => 'staff'],
    '919306260247'    => ['name' => 'Ansh', 'phone' => '919306260247', 'user_id' => 2, 'role' => 'staff'],
    '9306260247'      => ['name' => 'Ansh', 'phone' => '919306260247', 'user_id' => 2, 'role' => 'staff'],

    // Zainab (ID: 4)
    '917778080709'    => ['name' => 'Zainab', 'phone' => '917778080709', 'user_id' => 4, 'role' => 'staff'],
    '7778080709'      => ['name' => 'Zainab', 'phone' => '917778080709', 'user_id' => 4, 'role' => 'staff'],

    // Founder Nimit Thawrani Primary & Secondary (ID: 1)
    '971552489492'    => ['name' => 'Nimit Thawrani (Founder)', 'phone' => '971552489492', 'user_id' => 1, 'role' => 'founder'],
    '552489492'       => ['name' => 'Nimit Thawrani (Founder)', 'phone' => '971552489492', 'user_id' => 1, 'role' => 'founder'],
    '971552628603'    => ['name' => 'Nimit Thawrani (Founder)', 'phone' => '971552628603', 'user_id' => 1, 'role' => 'founder'],
    '552628603'       => ['name' => 'Nimit Thawrani (Founder)', 'phone' => '971552628603', 'user_id' => 1, 'role' => 'founder'],
];

$senderUser = null;
$matchedProfile = null;

// Check participant LID, cleanFrom, or last 9 digits against known team map
foreach ([$participantLid, $cleanFrom, substr($cleanFrom, -9)] as $k) {
    if (!empty($k) && isset($knownTeamMap[$k])) {
        $matchedProfile = $knownTeamMap[$k];
        break;
    }
}

if ($matchedProfile) {
    $senderName = $matchedProfile['name'];
    $cleanFrom  = $matchedProfile['phone'];
    $from       = $matchedProfile['phone'];
    $senderUser = Database::one("SELECT * FROM users WHERE id = ? LIMIT 1", [$matchedProfile['user_id']]);
    if ($senderUser && !empty($participantLid)) {
        Database::run("UPDATE users SET whatsapp_lid = ? WHERE id = ?", [$participantLid, $senderUser['id']]);
    }
} else {
    // 1. Try matching by WhatsApp LID in database
    if (!empty($participantLid)) {
        $senderUser = Database::one("SELECT * FROM users WHERE whatsapp_lid = ? AND status = 'active' LIMIT 1", [$participantLid]);
    }

    // 2. Try matching by phone number
    $last9 = strlen($cleanFrom) >= 9 ? substr($cleanFrom, -9) : $cleanFrom;
    if (!$senderUser && !empty($cleanFrom)) {
        $senderUser = Database::one(
            "SELECT * FROM users WHERE (REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ? OR phone LIKE ?) AND status = 'active' LIMIT 1",
            ["%$last9%", "%$cleanFrom%"]
        );
    }

    // 3. Try matching by sender's name
    if (!$senderUser && !empty($senderName) && $senderName !== 'Team Member') {
        $senderUser = Database::one(
            "SELECT * FROM users WHERE (name LIKE ? OR name = ?) AND status = 'active' LIMIT 1",
            ['%' . trim($senderName) . '%', trim($senderName)]
        );
        if ($senderUser && !empty($participantLid)) {
            Database::run("UPDATE users SET whatsapp_lid = ? WHERE id = ?", [$participantLid, $senderUser['id']]);
        }
    }

    if ($senderUser) {
        $senderName = $senderUser['name'];
        if (!empty($senderUser['phone'])) {
            $realPhone = preg_replace('/[^0-9]/', '', $senderUser['phone']);
            if (!empty($realPhone)) {
                $from = $realPhone;
                $cleanFrom = $realPhone;
            }
        }
    }
}

// Authorized Founder Phone Numbers (Both of Nimit's numbers + paired phone)
$founderPhones = [
    '971552489492', // Founder Nimit Primary
    '971552628603', // Founder Nimit Secondary
];
$dbFounderPhone = Database::scalar("SELECT `value` FROM app_settings WHERE `key` = 'whatsapp_founder_phone'");
if ($dbFounderPhone) {
    $cleanDbFounder = preg_replace('/[^0-9]/', '', $dbFounderPhone);
    if (!empty($cleanDbFounder) && !in_array($cleanDbFounder, $founderPhones, true)) {
        $founderPhones[] = $cleanDbFounder;
    }
}
$founderLast9 = array_map(function($p) { return substr($p, -9); }, $founderPhones);

$isFounder = in_array($cleanFrom, $founderPhones, true)
    || in_array(substr($cleanFrom, -9), $founderLast9, true)
    || ($senderUser && (int)$senderUser['is_founder'] === 1);

if ($isFounder) {
    $senderName = 'Nimit Thawrani (Founder)';
    if (!$senderUser) {
        $senderUser = Database::one("SELECT * FROM users WHERE is_founder = 1 ORDER BY id ASC LIMIT 1");
    }
}

// Security Pairing for Founder
$founderSecret = 'crm_founder_secure_hh_2026';
if (stripos($text, $founderSecret) !== false) {
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('whatsapp_founder_phone', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$from]);
    $welcomeMsg = "*Welcome Founder Nimit!*\n\nYour WhatsApp number (+{$from}) is now securely paired with *Hekayahaus CRM*.\n\nYou can chat naturally, assign tasks, request follow-ups, or use:\n• /overview - Live stats\n• /reports - Today's reports\n• /leads - Latest leads\n• /tasks - Overdue tasks\n• /clients - Deliverables progress";
    wa_send_message($source, $replyToJid, $welcomeMsg, $evoUrl, $evoKey, $evoInstance);
    echo json_encode(['ok' => true]);
    exit;
}

// Strict Privacy Guard: The bot NEVER speaks to anyone on private 1-on-1 chat except the Founder
// Log the incoming DM to wa_chat_history so Claude Code can read it, then exit without replying.
if (!$isGroup && !$isFounder) {
    wa_save_chat_history($replyToJid, $senderName, $cleanFrom, $text, false);
    wa_log("Ignored private DM from non-founder (+{$from})");
    echo json_encode(['ok' => true, 'ignored' => 'non_founder_private_chat']);
    exit;
}

// Handle `/setkey sk-ant-...`
if ($isFounder && preg_match('/^\/setkey\s+(\S+)/i', $text, $matches)) {
    $newAntKey = $matches[1];
    Database::run("INSERT INTO app_settings (`key`, `value`) VALUES ('anthropic_api_key', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$newAntKey]);
    wa_send_message($source, $replyToJid, "*Anthropic API Key Saved*\n\nClaude AI is now fully active for both 1-on-1 chats and group monitoring.", $evoUrl, $evoKey, $evoInstance);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── 4. INSTANT SHORTCUT COMMANDS (Founder 1-on-1) ────────────────────────────
if ($isFounder && !$isGroup && strpos($text, '/') === 0) {
    $cmd = strtolower(explode(' ', $text)[0]);
    switch ($cmd) {
        case '/overview':
        case '/status': {
            $totalLeads = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL");
            $todayReports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = CURDATE()");
            $pendingTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deleted_at IS NULL");
            $overdueTasks = (int)Database::scalar("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < NOW() AND deleted_at IS NULL");
            $activeClients = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL");

            $reply = "*Hekayahaus CRM -- Live Overview*\n\n"
                   . "Total Leads: {$totalLeads}\n"
                   . "Active Clients: {$activeClients}\n"
                   . "Pending Tasks: {$pendingTasks} (" . ($overdueTasks > 0 ? "{$overdueTasks} Overdue" : "0 Overdue") . ")\n"
                   . "Today's Reports: " . count($todayReports) . " submitted\n";
            if (!empty($todayReports)) {
                $reply .= "\n*Submitted Today:*\n";
                foreach ($todayReports as $tr) {
                    $reply .= "• " . $tr['user_name'] . "\n";
                }
            }
            wa_send_message($source, $replyToJid, $reply, $evoUrl, $evoKey, $evoInstance);
            echo json_encode(['ok' => true]);
            exit;
        }

        case '/reports':
        case '/today': {
            $today = date('Y-m-d');
            $reports = Database::all("SELECT dr.*, u.name AS user_name FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$today]);
            if (empty($reports)) {
                wa_send_message($source, $replyToJid, "*Daily Reports for Today ({$today})*\n\nNo reports submitted yet today.", $evoUrl, $evoKey, $evoInstance);
            } else {
                $reply = "*Daily Reports for Today ({$today})* -- " . count($reports) . " received:\n\n";
                foreach ($reports as $r) {
                    $reply .= "*" . $r['user_name'] . "*\n";
                    if (!empty($r['work_completed'])) {
                        $reply .= "Completed: " . substr(strip_tags($r['work_completed']), 0, 250) . "\n";
                    }
                    if (!empty($r['blockers']) && strtolower(trim($r['blockers'])) !== 'none' && strtolower(trim($r['blockers'])) !== 'no major blockers') {
                        $reply .= "Blockers: " . strip_tags($r['blockers']) . "\n";
                    }
                    $reply .= "\n";
                }
                wa_send_message($source, $replyToJid, $reply, $evoUrl, $evoKey, $evoInstance);
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        case '/clients': {
            $clients = Database::all("SELECT name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC LIMIT 10");
            $reply = "*Active Client Deliverables:*\n\n";
            foreach ($clients as $c) {
                $reply .= "• *" . ($c['company'] ?: $c['name']) . "*\n";
                $reply .= "  Reels: `{$c['reels_completed']}/{$c['reels_required']}` | Posts: `{$c['posts_completed']}/{$c['posts_required']}`\n";
            }
            wa_send_message($source, $replyToJid, $reply, $evoUrl, $evoKey, $evoInstance);
            echo json_encode(['ok' => true]);
            exit;
        }
    }
}

// ─── 5. CLAUDE AI BRAIN WITH CRM TOOLS ───────────────────────────────────────
if (empty($anthropicKey)) {
    if (!$isGroup) {
        wa_send_message($source, $replyToJid, "🤖 Send your Anthropic API Key to activate Claude AI:\n`/setkey sk-ant-...`\n\nOr use instant commands: /overview, /reports, /clients", $evoUrl, $evoKey, $evoInstance);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// Establish session
$founder = Database::one('SELECT * FROM users WHERE is_founder = 1 AND status = "active" ORDER BY id ASC LIMIT 1') ?: Database::one('SELECT * FROM users WHERE id = 1');
$_SESSION['user_id'] = (int)($senderUser['id'] ?? $founder['id']);

// Define CRM Tools for Claude
$claudeTools = [
    [
        'name' => 'crm_get_overview',
        'description' => 'Get real-time agency dashboard metrics: total leads, tasks, overdue tasks, today reports, active clients.',
        'input_schema' => ['type' => 'object', 'properties' => (object)[]]
    ],
    [
        'name' => 'crm_get_daily_reports',
        'description' => 'View daily work reports for any date. Today is ' . date('Y-m-d') . ', yesterday was ' . date('Y-m-d', strtotime('-1 day')) . '. Defaults to today.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD (e.g. yesterday is ' . date('Y-m-d', strtotime('-1 day')) . ', today is ' . date('Y-m-d') . '). Defaults to today.']
            ]
        ]
    ],
    [
        'name' => 'crm_submit_or_update_report',
        'description' => 'Log or update daily work report and blockers for a team member.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'integer', 'description' => 'User ID (defaults to sender)'],
                'work_completed' => ['type' => 'string', 'description' => 'Summary of tasks and deliverables completed'],
                'blockers' => ['type' => 'string', 'description' => 'Any blockers or issues reported'],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD date']
            ],
            'required' => ['work_completed']
        ]
    ],
    [
        'name' => 'crm_update_client_deliverables',
        'description' => 'Update or increment completed reels/posts for a client.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'client_name_or_id' => ['type' => 'string', 'description' => 'Client company name or ID'],
                'reels_completed' => ['type' => 'integer', 'description' => 'New total reels completed or increment amount'],
                'posts_completed' => ['type' => 'integer', 'description' => 'New total posts completed']
            ],
            'required' => ['client_name_or_id']
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
        'input_schema' => ['type' => 'object', 'properties' => (object)[]]
    ],
    [
        'name' => 'crm_get_member_tasks',
        'description' => 'Get active tasks and deadlines assigned to a specific team member.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'integer', 'description' => 'User ID of the team member'],
                'user_name' => ['type' => 'string', 'description' => 'Name of the member if user ID is unknown']
            ]
        ]
    ],
    [
        'name' => 'crm_update_task_status',
        'description' => 'Update status of an assigned task (e.g. mark completed when staff reports finish).',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer', 'description' => 'ID of the task to update'],
                'status' => ['type' => 'string', 'enum' => ['not_started', 'in_progress', 'completed']]
            ],
            'required' => ['task_id', 'status']
        ]
    ]
];

function execute_crm_tool_wa(string $name, array $args, ?array $senderUser, string $source, ?string $evoUrl, ?string $evoKey, ?string $evoInstance): array {
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
            $rawDate = trim($args['date'] ?? '');
            if (empty($rawDate) || strtolower($rawDate) === 'today') {
                $date = date('Y-m-d');
            } elseif (strtolower($rawDate) === 'yesterday') {
                $date = date('Y-m-d', strtotime('-1 day'));
            } else {
                $parsed = strtotime($rawDate);
                $date = $parsed ? date('Y-m-d', $parsed) : date('Y-m-d');
            }
            $reports = Database::all("SELECT dr.*, u.name AS user_name, u.phone AS user_phone FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$date]);
            $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
            $yesterdayReports = [];
            if ($date === date('Y-m-d')) {
                $yesterdayReports = Database::all("SELECT dr.*, u.name AS user_name, u.phone AS user_phone FROM daily_reports dr JOIN users u ON u.id = dr.user_id WHERE DATE(dr.report_date) = ? ORDER BY u.name ASC", [$yesterdayDate]);
            }
            return [
                'target_date' => $date,
                'target_day_name' => date('l, F j, Y', strtotime($date)),
                'reports_found' => count($reports),
                'reports' => $reports,
                'yesterday_date' => $yesterdayDate,
                'yesterday_reports_count' => count($yesterdayReports),
                'yesterday_reports' => $yesterdayReports
            ];

        case 'crm_submit_or_update_report':
            $targetUserId = !empty($args['user_id']) ? (int)$args['user_id'] : ($senderUser['id'] ?? $founder['id']);
            $reportDate = $args['date'] ?? date('Y-m-d');
            ReportModel::upsert($targetUserId, $reportDate, [
                'work_completed'  => $args['work_completed'] ?? '',
                'blockers'        => $args['blockers'] ?? 'None',
                'tasks_worked_on' => $args['work_completed'] ?? '',
            ]);
            $targetUser = Database::one("SELECT name, phone FROM users WHERE id = ?", [$targetUserId]);
            return [
                'success' => true,
                'crm_action' => 'logged_daily_report',
                'user_name' => $targetUser['name'] ?? 'Team Member',
                'date' => $reportDate,
                'work_logged' => $args['work_completed'] ?? ''
            ];

        case 'crm_update_client_deliverables':
            $ident = trim($args['client_name_or_id'] ?? '');
            $client = is_numeric($ident)
                ? Database::one("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL", [(int)$ident])
                : Database::one("SELECT * FROM clients WHERE (name LIKE ? OR company LIKE ?) AND deleted_at IS NULL LIMIT 1", ["%$ident%", "%$ident%"]);

            if (!$client) return ['error' => "Client '$ident' not found"];

            $updates = [];
            $params = [];
            if (isset($args['reels_completed'])) {
                $updates[] = 'reels_completed = ?';
                $params[] = (int)$args['reels_completed'];
            }
            if (isset($args['posts_completed'])) {
                $updates[] = 'posts_completed = ?';
                $params[] = (int)$args['posts_completed'];
            }
            if (!empty($updates)) {
                $params[] = $client['id'];
                Database::run("UPDATE clients SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            }
            $updatedClient = Database::one("SELECT id, name, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE id = ?", [$client['id']]);
            return [
                'success' => true,
                'crm_action' => 'updated_client_deliverables',
                'client_name' => $client['name'],
                'reels_completed' => (int)($updatedClient['reels_completed'] ?? 0),
                'reels_required' => (int)($updatedClient['reels_required'] ?? 0),
                'posts_completed' => (int)($updatedClient['posts_completed'] ?? 0),
                'posts_required' => (int)($updatedClient['posts_required'] ?? 0),
            ];

        case 'crm_list_leads':
            $search = $args['search'] ?? '';
            $where = ['deleted_at IS NULL'];
            $params = [];
            if (!empty($search)) {
                $where[] = '(name LIKE ? OR company LIKE ? OR phone LIKE ?)';
                $params = ["%$search%", "%$search%", "%$search%"];
            }
            return ['leads' => Database::all("SELECT id, name, company, phone, next_step, notes FROM leads WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 8", $params)];

        case 'crm_create_task':
            $taskId = TaskModel::create([
                'title' => $args['title'],
                'assigned_user_id' => $args['assigned_user_id'] ?? $founder['id'],
                'priority' => $args['priority'] ?? 'medium',
                'deadline' => $args['due_date'] ?? null,
                'status' => 'not_started',
                'created_by' => $founder['id']
            ]);
            $assignedUser = Database::one("SELECT name, phone FROM users WHERE id = ?", [(int)($args['assigned_user_id'] ?? $founder['id'])]);
            return [
                'success' => true,
                'crm_action' => 'created_task',
                'task_id' => $taskId,
                'task_title' => $args['title'],
                'assigned_to' => $assignedUser['name'] ?? 'Founder',
                'deadline' => $args['due_date'] ?? 'None'
            ];

        case 'crm_list_clients':
            return ['clients' => Database::all("SELECT id, name, company, reels_completed, reels_required, posts_completed, posts_required FROM clients WHERE status = 'active' AND deleted_at IS NULL LIMIT 15")];

        case 'crm_get_member_tasks':
            $targetUserId = null;
            if (!empty($args['user_id'])) {
                $targetUserId = (int)$args['user_id'];
            } elseif (!empty($args['user_name'])) {
                $u = Database::one("SELECT id FROM users WHERE name LIKE ? AND status = 'active' LIMIT 1", ['%' . trim($args['user_name']) . '%']);
                if ($u) $targetUserId = (int)$u['id'];
            } elseif ($senderUser) {
                $targetUserId = (int)$senderUser['id'];
            }
            if (!$targetUserId) return ['error' => 'Team member not identified'];
            $tasks = Database::all("SELECT id, title, priority, deadline, status FROM tasks WHERE assigned_user_id = ? AND status != 'completed' AND deleted_at IS NULL ORDER BY deadline ASC", [$targetUserId]);
            return ['user_id' => $targetUserId, 'active_tasks' => $tasks];

        case 'crm_update_task_status':
            $taskId = (int)($args['task_id'] ?? 0);
            $status = $args['status'] ?? 'completed';
            if ($taskId <= 0) return ['error' => 'Invalid task ID'];
            Database::run("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$status, $taskId]);
            return ['success' => true, 'task_id' => $taskId, 'new_status' => $status];

        default:
            return ['error' => 'Unknown tool'];
    }
}

// Check if bot was mentioned or tagged in this message
$botPhone = '971541311988';
$botLid = '121131717656587';
$mentionedJids = $payload['mentionedJidList']
    ?? ($payload['mentions']
    ?? ($payload['_data']['message']['extendedTextMessage']['contextInfo']['mentionedJid'] ?? []));

$isTagged = false;
if (is_array($mentionedJids)) {
    foreach ($mentionedJids as $mjid) {
        if (is_string($mjid) && (strpos($mjid, $botPhone) !== false || strpos($mjid, '541311988') !== false || strpos($mjid, $botLid) !== false)) {
            $isTagged = true;
            break;
        }
    }
}
if (!$isTagged) {
    $cleanTextDigits = preg_replace('/[^0-9]/', '', $text);
    if (strpos($cleanTextDigits, '541311988') !== false || strpos($cleanTextDigits, '971541311988') !== false || strpos($cleanTextDigits, $botLid) !== false) {
        $isTagged = true;
    } elseif (preg_match('/(@crm\s*bot|@crm|@bot|@alex|@hekayahaus|\bcrm\s*bot\b|\bcrmbot\b|\bcrm_bot\b|\balex\b|\bbot\b)/i', $text)) {
        $isTagged = true;
    }
}

// ─── GREETING DETECTION (Immediate, warm, human response) ─────────────────────
$cleanTextNoTags = trim(preg_replace('/@[a-zA-Z0-9_\-\.\s]+/iu', '', $text));
$cleanTextNoPunct = trim(preg_replace('/[[:punct:]]+/u', ' ', $cleanTextNoTags));
$cleanWords = trim(preg_replace('/\s+/', ' ', $cleanTextNoPunct));
$isGreetingWord = (bool)preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening)|namaste|ssup|howdy)\b/i', $cleanWords);
$isGreetingOnly = $isGreetingWord && (strlen($cleanWords) <= 22);

if ($isGreetingOnly && ($isTagged || !$isGroup)) {
    $greetingReply = "Hi {$senderName}! How can I help you today?";
    wa_save_chat_history($replyToJid, $senderName, $cleanFrom, $text, false);
    wa_save_chat_history($replyToJid, 'Alex (CRM Bot)', $botPhone, $greetingReply, true);

    $mentionsList = [];
    if (!empty($rawParticipant) && strpos($rawParticipant, '@') !== false) {
        $mentionsList[] = $rawParticipant;
    }
    wa_send_message($source, $replyToJid, $greetingReply, $evoUrl, $evoKey, $evoInstance, $mentionsList);

    try {
        Database::run("UPDATE wa_processed_messages SET status = 'completed' WHERE msg_id = ?", [$msgId]);
    } catch (Throwable $e) {}

    echo json_encode(['ok' => true]);
    exit;
}

// Save current user message to conversation history (ALWAYS read and remember all messages)
wa_save_chat_history($replyToJid, $senderName, $cleanFrom, $text, false);

// Strict Group Rule: In groups, NEVER reply or talk to anyone unless tagged.
// If untagged in a group: still read and remember the message in wa_chat_history.
// Only invoke Claude in SILENT OBSERVER MODE if the message appears to contain a work/CRM update.
$isSilentObserver = ($isGroup && !$isTagged);
if ($isSilentObserver) {
    $hasWorkSignal = (bool)preg_match('/\b(report|done|completed|finished|reel|reels|post|posts|task|tasks|client|clients|blocker|blockers|submitted|pending|update|updated|delivered|edited|script|shoot|upload|uploaded|lead|leads|meeting|status)\b/i', $text);
    if (!$hasWorkSignal) {
        try {
            Database::run("UPDATE wa_processed_messages SET status = 'context_saved_only' WHERE msg_id = ?", [$msgId]);
        } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'ignored' => 'untagged_group_saved_to_context']);
        exit;
    }
}

// Retrieve previous recent messages for conversation context memory (25-message rolling window)
$recentHistory = wa_get_chat_history($replyToJid, 25);
$conversationContext = "";
if (!empty($recentHistory)) {
    $conversationContext = "RECENT CONVERSATION CONTEXT (FOR CONTEXT & MEMORY ONLY):\n";
    foreach ($recentHistory as $hr) {
        $author = $hr['is_bot'] ? "Alex (CRM Bot)" : $hr['sender_name'];
        $conversationContext .= "{$author}: " . trim($hr['message']) . "\n";
    }
    $conversationContext .= "CRITICAL: You are responding ONLY to the latest message below from {$senderName}. NEVER re-answer past messages.\n\n";
}

// Bot Identity
$botIdentity = "YOUR IDENTITY & PHONE NUMBER:\n"
             . "You are Alex (Crm Bot), the Operations Lead at Hekayahaus CRM.\n"
             . "Your WhatsApp phone number is +971 54 131 1988 (+971 541311988 / 971541311988).\n"
             . "Whenever anyone mentions +971 54 131 1988, @+971541311988, @Crm Bot, or Alex, they are speaking directly to you.\n\n";

// Calendar Context
$todayDateStr = date('Y-m-d');
$todayReadable = date('l, F j, Y');
$yesterdayDateStr = date('Y-m-d', strtotime('-1 day'));
$yesterdayReadable = date('l, F j, Y', strtotime('-1 day'));
$currentTime = date('h:i A T');

$calendarContext = "CURRENT CALENDAR & TIME:\n"
                 . "- TODAY: {$todayReadable} ({$todayDateStr})\n"
                 . "- YESTERDAY: {$yesterdayReadable} ({$yesterdayDateStr})\n"
                 . "- CURRENT TIME: {$currentTime}\n"
                 . "When checking daily reports or tasks, ALWAYS use these exact dates.\n\n";

$safetyRules = "STRICT CORE RULES:\n"
             . "1. ZERO EMOJIS: Do NOT use any emojis anywhere in your messages. Use clean, professional text.\n"
             . "2. ZERO DELETIONS: Never delete, drop, purge, or remove any records or data from the CRM under any circumstances.\n"
             . "3. POLITE & HUMAN: Start conversations with a polite greeting (e.g. 'Hi {$senderName}'). Keep answers short, precise, conversational, and human (1 to 2 sentences maximum).\n"
             . "4. EXPLICIT CRM WRITE CONFIRMATION: Whenever you make any update or save to the CRM (such as logging daily work reports, incrementing client reels/posts deliverables, creating or updating tasks), you MUST explicitly state what was updated in the CRM (for example: 'I have logged your daily report for today in the CRM.').\n"
             . "5. TAGGING IN GROUPS: Address team members naturally by their name (e.g. 'Hi {$senderName}'). NEVER output phone numbers or LID codes like @+256... or @256... Always use their first name.\n"
             . "6. TALK ONLY ABOUT ASSIGNED WORK: In groups, only discuss tasks and client deliverables specifically assigned to that team member.\n"
             . "7. NOISE FILTER: In groups, if the message is casual banter, jokes, or general chatter and you are NOT tagged, output '[NO_REPLY]' so the group is never spammed. If tagged, you MUST reply.\n\n";

// Build Context-Aware System Prompt
if ($isGroup) {
    if ($isSilentObserver) {
        $tagNote = "SILENT OBSERVER MODE (CRITICAL): You were NOT tagged in this group message. NEVER reply or talk to anyone in the group. Do NOT write a conversational response. Only call CRM tools silently if {$senderName} reported completed work, a daily report, task progress, or client deliverables that should be updated in the CRM. After any tool call (or if no CRM update is needed), output ONLY '[NO_REPLY]'.\n";
    } else {
        $tagNote = "IMPORTANT: You were directly tagged or mentioned in this message. You MUST reply.\n";
    }
    $systemPrompt = $botIdentity
                  . $calendarContext
                  . $conversationContext
                  . $safetyRules
                  . $tagNote
                  . "You are Alex, the friendly Operations Lead at Hekayahaus CRM. You are reading this Team WhatsApp group in real time.\n"
                  . "Current Speaker: '{$senderName}' (Phone: +{$from}, User ID: " . ($senderUser['id'] ?? 'unmatched') . ").\n"
                  . "Always address them politely as {$senderName}, write clean human sentences without emojis, and explicitly confirm any updates made to the CRM.\n";
} else {
    $systemPrompt = $botIdentity
                  . $calendarContext
                  . $conversationContext
                  . $safetyRules
                  . "You are Alex, the AI Executive Operations Assistant for Founder Nimit Thawrani at Hekayahaus CRM.\n"
                  . "You are in a private 1-on-1 chat with the Founder. Be polite (e.g. 'Hi Nimit'), concise, and professional with ZERO emojis.\n"
                  . "Always confirm any CRM changes explicitly.\n";
}

// Call Claude Sonnet
$apiUrl = "https://api.anthropic.com/v1/messages";
$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'system' => $systemPrompt,
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

if (isset($claudeData['error']['message'])) {
    $finalText = "Claude API Error: " . $claudeData['error']['message'];
} elseif (isset($claudeData['content'])) {
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
        $toolResult = execute_crm_tool_wa($toolUseBlock['name'], $toolUseBlock['input'] ?? [], $senderUser, $source, $evoUrl, $evoKey, $evoInstance);

        if (!$isSilentObserver) {
            $followUpPayload = [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'system' => $systemPrompt . "\nCRITICAL: If you updated or saved anything to the CRM (such as daily report, client deliverables count, or task status), explicitly state what you updated in the CRM. Keep the response polite, natural, short (1 to 2 sentences), and do NOT use any emojis.",
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
}

// ─── OUTPUT SANITIZATION & SINGLE OUTBOUND SEND ──────────────────────────────
// Hard guard: NEVER send a message to a group unless directly tagged!
if (!$isSilentObserver && !empty($finalText) && stripos($finalText, '[NO_REPLY]') === false) {
    // 1. Strip any emojis
    $finalText = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}]/u', '', $finalText);

    // 2. Eradicate any accidental @+256... or +256... LID mentions
    $finalText = preg_replace('/@\+?256\s*[0-9]+/i', '@Ansh', $finalText);
    $finalText = preg_replace('/\+?256\s*[0-9]+/i', 'Ansh', $finalText);

    // 3. Clean whitespace
    $finalText = preg_replace('/[ \t]{2,}/', ' ', $finalText);
    $finalText = trim($finalText);

    // 4. Send message once
    $mentionsList = [];
    if (!empty($rawParticipant) && strpos($rawParticipant, '@') !== false) {
        $mentionsList[] = $rawParticipant;
    }
    wa_send_message($source, $replyToJid, $finalText, $evoUrl, $evoKey, $evoInstance, $mentionsList);

    // 5. Save bot reply to chat history
    wa_save_chat_history($replyToJid, 'Alex (CRM Bot)', $botPhone, $finalText, true);

    try {
        Database::run("UPDATE wa_processed_messages SET status = 'completed' WHERE msg_id = ?", [$msgId]);
    } catch (Throwable $e) {}
} elseif ($isSilentObserver) {
    try {
        Database::run("UPDATE wa_processed_messages SET status = 'silent_observer_done' WHERE msg_id = ?", [$msgId]);
    } catch (Throwable $e) {}
}

echo json_encode(['ok' => true]);
