# Real-Time Claude AI & MCP Integration for Agency CRM

Connect Anthropic's **Claude** (Claude Desktop, Claude Code, or Claude Web) directly to your CRM with **Founder** privileges.

---

## What Claude Can Do in Real Time
- 📊 **See Live Dashboard & Reports:** Ask *"What did the team work on today?"*, *"Show me all blockers"*, *"Which leads came in today?"*
- 🎯 **Manage Leads:** Ask *"Update lead Rohan to Closed and set next step to 'Onboarding call tomorrow'"*, *"Create a new lead from Instagram for client Acme"*
- ✅ **Assign & Update Tasks:** Ask *"Assign task 'Video Edit' to Ansh"*, *"Mark task #14 completed"*
- 📈 **Track Client Deliverables:** Ask *"How many reels has client XYZ completed this month?"*, *"Increment completed posts by 2"*
- 👑 **Founder-Level Control:** All actions execute as the Founder account and are automatically logged into the CRM's `audit_logs`.

---

## Setup in 2 Minutes

### 1. Locate Your Founder API Key
- Go to your CRM web app: **My Profile** (`index.php?page=profile`).
- Scroll down to the **Claude AI & Real-Time MCP Integration** section.
- Copy your **Founder API Key** (or use the one stored in `app_settings` / `config.php`).

### 2. Configure Claude Desktop
Open your Claude Desktop configuration file:
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

Add the `agency-crm` server under `mcpServers`:

```json
{
  "mcpServers": {
    "agency-crm": {
      "command": "python3",
      "args": [
        "/Users/anshbhatt/Desktop/hh/crm/mcp/crm_mcp.py"
      ],
      "env": {
        "CRM_API_URL": "https://your-crm-domain.com/api.php",
        "CRM_API_KEY": "YOUR_FOUNDER_API_KEY_HERE"
      }
    }
  }
}
```

> **Tip:** If running locally for testing, replace `CRM_API_URL` with `http://localhost:8000/api.php`.

### 3. Restart Claude Desktop
1. Completely quit Claude Desktop (`Cmd + Q` on macOS).
2. Reopen Claude Desktop.
3. Look for the 🔨 **hammer icon** at the bottom right of the chat bar. You will see all 13 CRM tools available!

---

## Using with Claude Code CLI
If you use Claude Code in your terminal, add the MCP server with one command:

```bash
claude mcp add agency-crm python3 /Users/anshbhatt/Desktop/hh/crm/mcp/crm_mcp.py \
  -e CRM_API_URL="https://your-crm-domain.com/api.php" \
  -e CRM_API_KEY="YOUR_FOUNDER_API_KEY_HERE"
```

---

## Direct REST API (cURL / Webhooks)
You can also call the API directly:

```bash
# Get agency overview
curl -H "Authorization: Bearer YOUR_FOUNDER_API_KEY" \
  "https://your-crm-domain.com/api.php?action=overview"

# Get today's daily reports
curl -H "Authorization: Bearer YOUR_FOUNDER_API_KEY" \
  "https://your-crm-domain.com/api.php?action=get_reports&date=2026-11-21"

# Update a lead
curl -X POST -H "Authorization: Bearer YOUR_FOUNDER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"action":"update_lead","id":5,"status_id":7,"notes":"Closed via Claude"}' \
  "https://your-crm-domain.com/api.php"
```

