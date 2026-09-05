# Agency CRM

A lightweight, self-contained CRM built for a digital/creative/marketing agency —
Founder, Managers, Teams, and Team Members all working out of **one central
database**. Different dashboards are just permission-aware views of the same
data; nothing is duplicated between departments.

Built with plain PHP 8.2+, PDO, MySQL/MariaDB, and vanilla JS/CSS — no
frameworks, no build step, no Node, no Composer required in production.
Runs on ordinary shared hosting (e.g. Hostinger Business Web Hosting).

---

## 1. Requirements

- PHP 8.1 or newer, with `pdo_mysql`, `mbstring`, and `fileinfo` extensions
  (all enabled by default on Hostinger).
- MySQL 5.7+ or MariaDB 10.3+.
- Apache with `.htaccess` support (`AllowOverride All` — the default on
  shared hosting control panels).
- No SSH, no Composer, no Node.js needed.

## 2. Deploying to Hostinger (or any shared host)

1. **Upload the files.** Zip the contents of this folder (not the folder
   itself — the files should sit directly inside) and upload/extract it into
   `public_html` (or a subfolder, e.g. `public_html/crm`, if you want the CRM
   at `yourdomain.com/crm`) via hPanel's File Manager or FTP.
2. **Create a MySQL database.** In hPanel &rarr; Databases &rarr; MySQL
   Databases, create a new database and a database user with full privileges
   on it. Note the database name, username, password, and host (usually
   `localhost` on Hostinger).
3. **Run the installer.** Visit `https://yourdomain.com/install.php` (or
   `https://yourdomain.com/crm/install.php`). It will:
   - Ask for your database host/name/user/password and test the connection.
   - Create the database schema automatically (imports `database/schema.sql`).
   - Write `config/config.php` for you.
   - Walk you through creating the first **Founder** account.
4. **Delete `install.php`** from the server once setup is complete (the
   installer also refuses to run again after installation, as a safety net).
5. **Log in** at `index.php` with the Founder account you just created.

That's it — upload, create database, run installer, create Founder account,
log in.

### Manual install (alternative to the web installer)

If you prefer, you can import `database/schema.sql` yourself via
phpMyAdmin, then copy `config/config.sample.php` to `config/config.php` and
fill in your database credentials and a random `secret` string. You will
then need to manually insert a Founder user (with a bcrypt/argon2 password
hash) and assign the `founder` role via the `user_roles` table, or simply
run `install.php` and let it do this for you (it detects an already-imported
schema and skips re-importing it).

## 3. Configuration

All configuration lives in `config/config.php` (created by the installer,
never committed to version control). See `config/config.sample.php` for the
format. Key settings:

- `db.host` / `db.name` / `db.user` / `db.pass` — database credentials.
- `app.url` — the base URL of your installation (used for links).
- `app.timezone` — defaults to `Asia/Kolkata`; all dates/times are stored
  and displayed consistently in this timezone.
- `app.debug` — keep `false` in production. When `true`, PHP errors are
  displayed on screen instead of being logged silently (only use this
  temporarily while diagnosing an issue).

## 4. Database Structure (overview)

Everything hangs off a small number of central tables — there is **one**
`clients` table, **one** `leads` table, **one** `tasks` table, etc. Every
department's dashboard queries these same tables through permission-aware
filters; nothing is copied into department-specific tables.

| Table | Purpose |
|---|---|
| `users`, `roles`, `permissions`, `user_roles`, `role_permissions` | Identity + the permission engine. A user can hold multiple roles; each role grants a set of permissions. |
| `teams`, `team_members`, `team_managers` | Hierarchy. A team has members and one or more managers; a manager only sees the members of teams they manage. |
| `leads`, `lead_statuses`, `lead_import_batches` | Sales pipeline. |
| `clients`, `services`, `client_services`, `client_service_assignments` | The central Client + Work model: a client has services with required/completed quantities, and each service's work can be split across multiple employees. |
| `tasks`, `task_assignments` | Work items, optionally linked to a client/service, with full reassignment history. |
| `activities` | A single polymorphic activity timeline table (`entity_type` + `entity_id`) used by leads, clients, and tasks alike. |
| `calendar_events`, `founder_availability` | Personal calendars and the Founder's shared availability calendar. |
| `daily_reports`, `leave_requests` | Employee daily reports and leave management. |
| `notifications` | Lightweight in-app notifications (no real-time infra — checked on page load). |
| `audit_logs` | Immutable record of who did what, when, to what, with old/new values. |

Soft deletes (`deleted_at`) are used on `users`, `leads`, `clients`,
`tasks`, and `teams` so historical data is never destroyed by normal use.

## 5. Permission Architecture

Authorization is **never** based on role names alone, and **never** enforced
only in the UI. Every controller calls into `core/Permission.php`, which
checks:

```
ROLE  +  PERMISSION  +  HIERARCHY  +  ASSIGNMENT
```

- **Permissions** (`leads.view`, `leads.view_all`, `clients.manage_services`,
  `tasks.assign`, `users.manage`, etc.) are attached to **roles**, and roles
  are attached to **users** — all editable from Roles & Permissions as the
  Founder. There is no hard-coded role logic anywhere in the codebase.
- **Hierarchy** comes from `team_managers` (which manager manages which
  team) and `team_members` (who's on the team). `Permission::managedUserIds()`
  resolves this at query time.
- **Assignment** is the actual `assigned_user_id` / `client_service_assignments`
  data on each record.
- A user who does **not** hold a `*.view_all` permission only ever sees rows
  where they are the assignee, the creator, or where they manage the
  assignee through a team. This filter is applied **inside the SQL query
  itself** (see `LeadModel::paginate()`, `TaskModel::paginate()`,
  `Permission::clientVisibility()`), so there is no way to bypass it via
  direct URLs, AJAX, search, export, or a manually-edited ID — every
  `*Model::canAccess()` / `Permission::canAccessClient()` check is repeated
  in the controller before any read or write.
- The Founder flag (`users.is_founder`) is a belt-and-suspenders override
  that always grants full access — but it is itself a database-driven flag
  set only via the installer or another Founder, not a hard-coded username.

## 6. Role Hierarchy

```
FOUNDER
   └── MANAGER  (manages one or more Teams)
          └── TEAM MEMBER  (one or more roles: Sales, Editor, Videographer, ...)
```

- Founder, Manager are system roles seeded by the installer. All other
  roles (Sales, Editor, Videographer, Social Media, Designer, SEO, etc.)
  are ordinary configurable roles — the Founder can rename, add, remove, or
  re-permission any of them from **Roles & Permissions** without touching
  code.
- A user can hold multiple roles simultaneously; their dashboard and
  sidebar automatically union the relevant sections for every role they
  hold (e.g. an Editor + Videographer sees both editing and shoot work, but
  nothing from Sales).
- A Manager only manages the teams explicitly assigned to them
  (`team_managers`) — they do **not** automatically inherit Founder-level
  visibility, and a private task created by the Founder stays invisible to
  Managers unless explicitly shared.

## 7. Security Notes

- All database access goes through PDO prepared statements
  (`core/Database.php`) — no string-concatenated SQL anywhere.
- Every state-changing POST request is protected by a per-session CSRF
  token (`core/Csrf.php`).
- All user-supplied output is escaped with `e()` (a `htmlspecialchars`
  wrapper) before being echoed into HTML.
- Sessions are cookie-based, `HttpOnly`, `SameSite=Lax`, regenerated on
  login, and destroyed fully on logout.
- Passwords are hashed with PHP's `password_hash()` (bcrypt) — never
  stored or logged in plain text.
- IDOR protection: every "view"/"edit"/"delete" controller action re-checks
  `canAccess()`/`Permission::has()` against the **current session user**,
  never trusting a role or ID supplied by the client.
- CSV import is restricted to `.csv` files under 5 MB, checked by both
  extension and MIME type, stored under a random filename in `/uploads`
  (which itself denies all direct web access), and deleted immediately
  after processing.
- PHP errors and SQL exceptions are logged to `storage/logs/app.log` and
  never shown to the browser (`fatal_error()` always shows a generic
  message in production).
- `config/`, `core/`, `models/`, `controllers/`, `views/`, `database/`,
  `storage/`, and `uploads/` all carry their own `Require all denied`
  `.htaccess`, so none of that PHP/SQL/log content is directly reachable
  over HTTP even if someone guesses the path.

## 8. Backup

No custom backup server is included — use the tools your host already
gives you:

- **phpMyAdmin export**: hPanel &rarr; Databases &rarr; phpMyAdmin &rarr;
  select your database &rarr; Export &rarr; Quick &rarr; SQL &rarr; Go.
  Keep the resulting `.sql` file somewhere safe (and off the server).
- **Restoring**: create a fresh database, open phpMyAdmin on it, and use
  Import to load the `.sql` file back in.
- Back up the whole application folder periodically too (via FTP or
  hPanel's File Manager "Compress") — this captures `config/config.php`
  and anything in `storage/logs`.

## 9. Updating

Because there's no build step, updating is just replacing PHP files: upload
the new files over the old ones (everything except `config/config.php`,
which holds your live credentials — never overwrite it), then re-check
`database/schema.sql` for any new `ALTER TABLE` statements you need to run
if a future version adds tables/columns.

## 10. Troubleshooting

- **"Unable to connect to the database"** — double-check the credentials in
  `config/config.php`; on Hostinger the DB host is almost always
  `localhost` and the DB user/name are usually prefixed with your hosting
  username.
- **Blank white page** — check `storage/logs/app.log` for the actual PHP
  error (nothing is ever shown to visitors in production).
- **"You do not have permission to perform this action"** — this is the
  permission engine working as intended; check the user's roles under
  Users, and that role's permissions under Roles & Permissions.
- **CSV import says every row is a duplicate** — the importer matches on
  phone number (last 10 digits) and email; if you're re-importing the same
  list, that's expected — check the Duplicates count and existing Lead IDs
  shown in the import history rather than the confirmation screen.
- **install.php says "Already Installed"** — this is expected once setup
  is complete; delete the file. If you genuinely need to reinstall, delete
  `config/config.php` and `config/installed.lock` first (this does **not**
  drop your existing database tables/data).

## 11. Project Structure

```
config/       configuration (config.php is created by the installer)
core/         framework-ish plumbing: DB, Auth, Permission, CSRF, Validator, helpers
models/       one class per entity, all data-access + business rules
controllers/  one file per top-level menu item, dispatches on ?action=
views/        server-rendered PHP templates, split into layout/ + per-module folders
assets/       CSS + vanilla JS (no build step, no CDN dependency)
database/     schema.sql (also used by the installer)
uploads/      temporary CSV import storage (denied to the web, auto-cleaned)
storage/logs/ application error log (denied to the web)
index.php     single front controller (?page=...&action=...)
install.php   one-time web installer — delete after use
```
