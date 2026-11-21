<h1>My Profile</h1>
<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Account Details</div>
        <table>
            <tr><td class="text-muted">Employee Code</td><td><?= e($user['employee_code']) ?></td></tr>
            <tr><td class="text-muted">Name</td><td><?= e($user['name']) ?></td></tr>
            <tr><td class="text-muted">Email</td><td><?= e($user['email']) ?></td></tr>
            <tr><td class="text-muted">Roles</td><td><?= e(implode(', ', array_column($roles, 'name'))) ?: '—' ?></td></tr>
            <tr><td class="text-muted">Teams</td><td><?= e(implode(', ', array_column($teams, 'name'))) ?: '—' ?></td></tr>
        </table>
        <hr>
        <form method="post" action="<?= url('profile', ['action' => 'update']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
            <button class="btn btn-primary btn-sm">Save</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Change Password</div>
        <?php if ($user['must_change_password']): ?><p class="text-danger small">Your administrator has reset your password. Please set a new one.</p><?php endif; ?>
        <form method="post" action="<?= url('profile', ['action' => 'change_password']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="8"></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8"></div>
            <button class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>

<?php if (!empty($founderApiKey)): ?>
    <?php
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $apiUrl = $protocol . '://' . $currentHost . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api.php';
        $mcpScriptPath = realpath(__DIR__ . '/../../mcp/crm_mcp.py') ?: (dirname(__DIR__, 2) . '/mcp/crm_mcp.py');

        $claudeConfig = [
            'mcpServers' => [
                'agency-crm' => [
                    'command' => 'python3',
                    'args' => [$mcpScriptPath],
                    'env' => [
                        'CRM_API_URL' => $apiUrl,
                        'CRM_API_KEY' => $founderApiKey,
                    ]
                ]
            ]
        ];
        $claudeConfigJson = json_encode($claudeConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    ?>
    <div class="card" style="margin-top: 24px; border: 2px solid var(--primary); background: var(--surface);">
        <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>🤖 Claude AI & Real-Time MCP Integration (Founder Access)</span>
            <span class="badge badge-success">Founder API Ready</span>
        </div>
        <p class="text-muted" style="margin-top:-6px;">
            Connect Claude Desktop or Claude Code to this CRM. Claude will have real-time visibility into leads, daily reports, tasks, and client deliverables, and can make changes directly as your Founder account.
        </p>

        <div style="background:var(--bg-hover); padding:16px; border-radius:6px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong>Founder API Key:</strong>
                <form method="post" action="<?= url('profile', ['action' => 'regenerate_api_key']) ?>" style="margin:0;" onsubmit="return confirm('Regenerate Founder API Key? Any existing Claude connections will need the new key.');">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-secondary" style="font-size:11px;">Regenerate Key</button>
                </form>
            </div>
            <div style="display:flex; gap:8px;">
                <input type="text" id="founderApiKeyInput" readonly value="<?= e($founderApiKey) ?>" class="form-control" style="font-family:monospace; background:#fff;">
                <button type="button" class="btn btn-primary btn-sm" onclick="copyApiKey()">Copy Key</button>
            </div>
            <small class="text-muted" style="display:block; margin-top:6px;">API Endpoint URL: <code><?= e($apiUrl) ?></code></small>
        </div>

        <h4 style="margin-top:20px; margin-bottom:8px;">How to Connect Claude Desktop in 2 Minutes:</h4>
        <ol style="margin-bottom:16px; padding-left:20px; font-size:13px; line-height:1.6;">
            <li>Open Claude Desktop settings or navigate to your Claude configuration file:<br>
                <strong>macOS:</strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code><br>
                <strong>Windows:</strong> <code>%APPDATA%\Claude\claude_desktop_config.json</code>
            </li>
            <li>Paste the following JSON configuration into your <code>claude_desktop_config.json</code>:</li>
        </ol>

        <div style="position:relative; margin-bottom:16px;">
            <pre style="background:#1e1e2e; color:#cdd6f4; padding:16px; border-radius:6px; font-size:12px; overflow-x:auto;" id="claudeConfigBox"><?= e($claudeConfigJson) ?></pre>
            <button type="button" class="btn btn-sm btn-secondary" onclick="copyClaudeConfig()" style="position:absolute; top:8px; right:8px; font-size:11px;">Copy Config JSON</button>
        </div>

        <p class="small text-muted" style="margin-bottom:0;">
            Restart Claude Desktop. A hammer icon will appear in chat! You can then ask Claude: <em>"Show me today's daily reports"</em>, <em>"What leads need follow up?"</em>, or <em>"Assign the video edit task to Ansh"</em>.
        </p>
    </div>

    <script>
    function copyApiKey() {
        const input = document.getElementById('founderApiKeyInput');
        input.select();
        document.execCommand('copy');
        alert('Founder API Key copied to clipboard!');
    }
    function copyClaudeConfig() {
        const text = document.getElementById('claudeConfigBox').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Claude Desktop configuration JSON copied to clipboard!');
        });
    }
    </script>
<?php endif; ?>
