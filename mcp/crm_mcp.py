#!/usr/bin/env python3
"""
Agency CRM - Realtime Model Context Protocol (MCP) Server for Claude Desktop & Claude Code
Zero-dependency, pure Python 3 stdlib implementation.

Allows Claude to:
- See real-time agency leads, tasks, team reports, and client deliverables
- Make changes (create leads, assign tasks, update reports, edit deliverables)
- Act with full Founder-level privileges in real-time
"""

import sys
import json
import os
import urllib.request
import urllib.error
import ssl

# Create resilient SSL context (handles macOS missing certifi bundle)
ssl_context = ssl.create_default_context()
try:
    import certifi
    ssl_context.load_verify_locations(certifi.where())
except Exception:
    ssl_context.check_hostname = False
    ssl_context.verify_mode = ssl.CERT_NONE

# Determine CRM Base API URL and Key from environment or local config
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_FILE = os.path.join(SCRIPT_DIR, "config.json")

crm_url = os.environ.get("CRM_API_URL", "")
crm_key = os.environ.get("CRM_API_KEY", "")

if (not crm_url or not crm_key) and os.path.exists(CONFIG_FILE):
    try:
        with open(CONFIG_FILE, "r") as f:
            cfg = json.load(f)
            crm_url = crm_url or cfg.get("crm_api_url", "")
            crm_key = crm_key or cfg.get("crm_api_key", "")
    except Exception:
        pass

# Fallbacks if running on local dev server
if not crm_url:
    crm_url = "https://limegreen-jaguar-275547.hostingersite.com/api.php"

def call_crm_api(action, params=None):
    """Call the CRM api.php endpoint securely."""
    if params is None:
        params = {}
    
    payload = {"action": action, **params}
    data_bytes = json.dumps(payload).encode("utf-8")
    
    req = urllib.request.Request(
        crm_url,
        data=data_bytes,
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {crm_key}",
            "User-Agent": "Claude-CRM-MCP/1.0"
        },
        method="POST"
    )
    
    try:
        with urllib.request.urlopen(req, context=ssl_context, timeout=15) as resp:
            body = resp.read().decode("utf-8")
            return json.loads(body)
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        try:
            return json.loads(err_body)
        except Exception:
            return {"success": False, "error": f"HTTP {e.code}: {err_body}"}
    except Exception as e:
        return {"success": False, "error": str(e)}

TOOLS = [
    {
        "name": "crm_get_overview",
        "description": "Fetch real-time agency dashboard summary: total leads, tasks counts, overdue tasks, today's daily reports, active clients, and critical issues.",
        "inputSchema": {
            "type": "object",
            "properties": {}
        }
    },
    {
        "name": "crm_list_leads",
        "description": "Search and list agency leads with status, assigned staff member, phone, email, notes, and next steps.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "search": {"type": "string", "description": "Search keyword by lead name, phone, email, or company"},
                "status_id": {"type": "integer", "description": "Filter by lead status ID"},
                "folder_id": {"type": "integer", "description": "Filter by sheet or folder ID (e.g. Sheet 1-7)"},
                "limit": {"type": "integer", "description": "Number of leads to return (default 25, max 100)"}
            }
        }
    },
    {
        "name": "crm_create_lead",
        "description": "Create a new lead in the CRM as Founder in real time.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "name": {"type": "string", "description": "Lead or prospect full name (required)"},
                "phone": {"type": "string", "description": "Phone number"},
                "email": {"type": "string", "description": "Email address"},
                "company": {"type": "string", "description": "Company or brand name"},
                "source": {"type": "string", "description": "Lead source (e.g. Instagram, Referral, Outbound)"},
                "status_id": {"type": "integer", "description": "Status ID (defaults to initial status)"},
                "assigned_user_id": {"type": "integer", "description": "Assigned employee ID"},
                "folder_id": {"type": "integer", "description": "Folder/Sheet ID"},
                "next_step": {"type": "string", "description": "Next scheduled action"},
                "notes": {"type": "string", "description": "Notes or lead requirements"}
            },
            "required": ["name"]
        }
    },
    {
        "name": "crm_update_lead",
        "description": "Update any field of a lead in real time (status, assignment, notes, next step, phone, email).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "id": {"type": "integer", "description": "Lead ID to update (required)"},
                "status_id": {"type": "integer", "description": "New status ID"},
                "assigned_user_id": {"type": "integer", "description": "New assigned user ID"},
                "next_step": {"type": "string", "description": "Updated next step"},
                "notes": {"type": "string", "description": "Updated notes"},
                "next_followup_date": {"type": "string", "description": "Next follow-up date (YYYY-MM-DD)"},
                "company": {"type": "string", "description": "Company name"},
                "phone": {"type": "string", "description": "Phone number"},
                "email": {"type": "string", "description": "Email"}
            },
            "required": ["id"]
        }
    },
    {
        "name": "crm_list_tasks",
        "description": "List agency tasks, filter by status ('pending', 'in_progress', 'completed'), user, or client.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "status": {"type": "string", "enum": ["pending", "in_progress", "completed", "cancelled", ""], "description": "Filter by task status"},
                "assigned_user_id": {"type": "integer", "description": "Filter by assigned user ID"},
                "client_id": {"type": "integer", "description": "Filter by client ID"},
                "search": {"type": "string", "description": "Search in task title or description"},
                "limit": {"type": "integer", "description": "Max tasks to return (default 25)"}
            }
        }
    },
    {
        "name": "crm_create_task",
        "description": "Create and assign a new task to any team member as Founder.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "title": {"type": "string", "description": "Task title (required)"},
                "description": {"type": "string", "description": "Detailed task instructions"},
                "assigned_user_id": {"type": "integer", "description": "User ID to assign task to"},
                "client_id": {"type": "integer", "description": "Associated client ID"},
                "due_date": {"type": "string", "description": "Due date (YYYY-MM-DD)"},
                "priority": {"type": "string", "enum": ["low", "medium", "high", "urgent"], "description": "Priority level"}
            },
            "required": ["title"]
        }
    },
    {
        "name": "crm_update_task",
        "description": "Update task status ('completed', 'in_progress', 'pending'), reassign, or update deadline.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "id": {"type": "integer", "description": "Task ID (required)"},
                "status": {"type": "string", "enum": ["pending", "in_progress", "completed", "cancelled"], "description": "New status"},
                "assigned_user_id": {"type": "integer", "description": "Reassign to user ID"},
                "priority": {"type": "string", "enum": ["low", "medium", "high", "urgent"], "description": "Priority level"},
                "due_date": {"type": "string", "description": "Due date (YYYY-MM-DD)"}
            },
            "required": ["id"]
        }
    },
    {
        "name": "crm_get_daily_reports",
        "description": "View daily work reports for any date, see what each employee completed, tasks worked on, pending work, and blockers.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "date": {"type": "string", "description": "Specific date (YYYY-MM-DD). Defaults to today if omitted."},
                "user_id": {"type": "integer", "description": "Filter by specific user ID"},
                "date_from": {"type": "string", "description": "Start date for range (YYYY-MM-DD)"},
                "date_to": {"type": "string", "description": "End date for range (YYYY-MM-DD)"}
            }
        }
    },
    {
        "name": "crm_submit_daily_report",
        "description": "Submit or update a daily work report in real time for Founder or on behalf of any team member.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "report_date": {"type": "string", "description": "Report date (YYYY-MM-DD). Defaults to today."},
                "user_id": {"type": "integer", "description": "User ID (defaults to founder)"},
                "work_completed": {"type": "string", "description": "Summary of work completed"},
                "tasks_worked_on": {"type": "string", "description": "Specific tasks worked on"},
                "pending_work": {"type": "string", "description": "Pending items for next day"},
                "blockers": {"type": "string", "description": "Any blockers or issues faced"},
                "notes": {"type": "string", "description": "Additional notes"}
            }
        }
    },
    {
        "name": "crm_list_clients",
        "description": "List active agency clients and their monthly deliverable progress (reels and posts completed vs required).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "search": {"type": "string", "description": "Search client name or company"}
            }
        }
    },
    {
        "name": "crm_update_client_deliverables",
        "description": "Update completed deliverable quantities (reels completed, posts completed) for any client.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "client_id": {"type": "integer", "description": "Client ID (required)"},
                "reels_completed": {"type": "integer", "description": "Number of completed reels"},
                "posts_completed": {"type": "integer", "description": "Number of completed posts"},
                "reels_required": {"type": "integer", "description": "Monthly required reels"},
                "posts_required": {"type": "integer", "description": "Monthly required posts"}
            },
            "required": ["client_id"]
        }
    },
    {
        "name": "crm_get_team_members",
        "description": "List all team members, employee codes, emails, and their assigned agency roles.",
        "inputSchema": {
            "type": "object",
            "properties": {}
        }
    },
    {
        "name": "crm_sql_query",
        "description": "Execute a raw SQL query on the CRM database as Founder (supports SELECT, UPDATE, etc.). Use for custom analytical lookups.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "query": {"type": "string", "description": "SQL query to execute (required)"}
            },
            "required": ["query"]
        }
    }
]

def handle_request(req):
    method = req.get("method")
    req_id = req.get("id")

    if method == "initialize":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "protocolVersion": "2024-11-05",
                "capabilities": {
                    "tools": {}
                },
                "serverInfo": {
                    "name": "agency-crm-mcp",
                    "version": "1.0.0"
                }
            }
        }

    elif method == "notifications/initialized":
        return None

    elif method == "tools/list":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "tools": TOOLS
            }
        }

    elif method == "tools/call":
        params = req.get("params", {})
        tool_name = params.get("name")
        args = params.get("arguments", {})

        action_map = {
            "crm_get_overview": "overview",
            "crm_list_leads": "get_leads",
            "crm_create_lead": "create_lead",
            "crm_update_lead": "update_lead",
            "crm_list_tasks": "get_tasks",
            "crm_create_task": "create_task",
            "crm_update_task": "update_task",
            "crm_get_daily_reports": "get_reports",
            "crm_submit_daily_report": "submit_report",
            "crm_list_clients": "get_clients",
            "crm_update_client_deliverables": "update_client_deliverables",
            "crm_get_team_members": "get_team",
            "crm_sql_query": "sql_query",
        }

        action = action_map.get(tool_name)
        if not action:
            return {
                "jsonrpc": "2.0",
                "id": req_id,
                "result": {
                    "content": [{"type": "text", "text": f"Error: Unknown tool '{tool_name}'"}],
                    "isError": True
                }
            }

        res = call_crm_api(action, args)
        is_error = not res.get("success", False)
        formatted_output = json.dumps(res, indent=2, ensure_ascii=False)

        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "content": [{"type": "text", "text": formatted_output}],
                "isError": is_error
            }
        }

    elif method == "ping":
        return {"jsonrpc": "2.0", "id": req_id, "result": {}}

    else:
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "error": {"code": -32601, "message": f"Method '{method}' not found"}
        }

def main():
    while True:
        try:
            line = sys.stdin.readline()
            if not line:
                break
            line = line.strip()
            if not line:
                continue

            req = json.loads(line)
            resp = handle_request(req)
            if resp is not None:
                sys.stdout.write(json.dumps(resp) + "\n")
                sys.stdout.flush()
        except KeyboardInterrupt:
            break
        except Exception as e:
            err_resp = {
                "jsonrpc": "2.0",
                "id": None,
                "error": {"code": -32603, "message": f"Internal MCP server error: {str(e)}"}
            }
            sys.stdout.write(json.dumps(err_resp) + "\n")
            sys.stdout.flush()

if __name__ == "__main__":
    main()

