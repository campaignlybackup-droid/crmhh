-- ============================================================
-- Agency CRM - Database Schema
-- MySQL 5.7+/MariaDB 10.3+ compatible
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- USERS & AUTH
-- ------------------------------------------------------------

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_founder TINYINT(1) NOT NULL DEFAULT 0,
    manager_id INT UNSIGNED DEFAULT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_manager (manager_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    `group` VARCHAR(40) NOT NULL DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TEAMS
-- ------------------------------------------------------------

CREATE TABLE teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE team_members (
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    CONSTRAINT fk_tm_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE team_managers (
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    can_approve_leave TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (team_id, user_id),
    CONSTRAINT fk_tmg_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_tmg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- LEADS
-- ------------------------------------------------------------

CREATE TABLE lead_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#6c757d',
    sort_order INT NOT NULL DEFAULT 0,
    is_won TINYINT(1) NOT NULL DEFAULT 0,
    is_lost TINYINT(1) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    company VARCHAR(150) DEFAULT NULL,
    source VARCHAR(80) DEFAULT NULL,
    status_id INT UNSIGNED NOT NULL,
    assigned_user_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    next_followup_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    converted_client_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    KEY idx_leads_phone (phone),
    KEY idx_leads_email (email),
    KEY idx_leads_assigned (assigned_user_id),
    KEY idx_leads_status (status_id),
    KEY idx_leads_created (created_at),
    CONSTRAINT fk_leads_status FOREIGN KEY (status_id) REFERENCES lead_statuses(id),
    CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_leads_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lead_folders_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_folder_users (
    folder_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (folder_id, user_id),
    CONSTRAINT fk_lfu_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_lfu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE leads ADD COLUMN folder_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE leads ADD CONSTRAINT fk_leads_folder FOREIGN KEY (folder_id) REFERENCES lead_folders(id) ON DELETE SET NULL;


CREATE TABLE lead_import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imported_by INT UNSIGNED DEFAULT NULL,
    filename VARCHAR(255) DEFAULT NULL,
    total_rows INT UNSIGNED DEFAULT 0,
    new_count INT UNSIGNED DEFAULT 0,
    updated_count INT UNSIGNED DEFAULT 0,
    duplicate_count INT UNSIGNED DEFAULT 0,
    invalid_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CLIENTS / SERVICES / WORK
-- ------------------------------------------------------------

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    company VARCHAR(150) DEFAULT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    website VARCHAR(150) DEFAULT NULL,
    status ENUM('active','inactive','on_hold') NOT NULL DEFAULT 'active',
    start_date DATE DEFAULT NULL,
    renewal_date DATE DEFAULT NULL,
    retention_date DATE DEFAULT NULL,
    drive_link VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    source_lead_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    KEY idx_clients_status (status),
    KEY idx_clients_renewal (renewal_date),
    CONSTRAINT fk_clients_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE leads ADD CONSTRAINT fk_leads_client FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL;

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    unit_label VARCHAR(40) NOT NULL DEFAULT 'units',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE client_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    quantity_required INT UNSIGNED NOT NULL DEFAULT 0,
    quantity_completed INT UNSIGNED NOT NULL DEFAULT 0,
    manager_id INT UNSIGNED DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    scope_details JSON DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    KEY idx_cs_client (client_id),
    KEY idx_cs_service (service_id),
    KEY idx_cs_manager (manager_id),
    CONSTRAINT fk_cs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_cs_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE client_service_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_service_id INT UNSIGNED NOT NULL,
    requirement_name VARCHAR(150) NOT NULL DEFAULT 'Requirement',
    user_id INT UNSIGNED NOT NULL,
    quantity_assigned INT UNSIGNED DEFAULT NULL,
    quantity_completed INT UNSIGNED NOT NULL DEFAULT 0,
    deadline DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_csa_cs (client_service_id),
    KEY idx_csa_user (user_id),
    CONSTRAINT fk_csa_cs FOREIGN KEY (client_service_id) REFERENCES client_services(id) ON DELETE CASCADE,
    CONSTRAINT fk_csa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TASKS
-- ------------------------------------------------------------

CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    client_service_id INT UNSIGNED DEFAULT NULL,
    service_id INT UNSIGNED DEFAULT NULL,
    assigned_user_id INT UNSIGNED DEFAULT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('not_started','in_progress','pending_review','completed','blocked','cancelled') NOT NULL DEFAULT 'not_started',
    start_date DATE DEFAULT NULL,
    deadline DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    KEY idx_tasks_assigned (assigned_user_id),
    KEY idx_tasks_client (client_id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_deadline (deadline),
    CONSTRAINT fk_tasks_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_cs FOREIGN KEY (client_service_id) REFERENCES client_services(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assignedby FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    from_user_id INT UNSIGNED DEFAULT NULL,
    to_user_id INT UNSIGNED DEFAULT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ta_task (task_id),
    CONSTRAINT fk_ta_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ACTIVITY TIMELINE (polymorphic: lead / task / client)
-- ------------------------------------------------------------

CREATE TABLE activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    note TEXT DEFAULT NULL,
    old_value VARCHAR(255) DEFAULT NULL,
    new_value VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activities_entity (entity_type, entity_id),
    KEY idx_activities_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CALENDAR / AVAILABILITY
-- ------------------------------------------------------------

CREATE TABLE calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    event_type ENUM('task','deadline','meeting','shoot','event') NOT NULL DEFAULT 'event',
    related_type VARCHAR(20) DEFAULT NULL,
    related_id INT UNSIGNED DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME DEFAULT NULL,
    location VARCHAR(200) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ce_user (user_id),
    KEY idx_ce_start (start_datetime),
    CONSTRAINT fk_ce_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE founder_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    founder_user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status ENUM('available','unavailable','busy','meeting') NOT NULL DEFAULT 'available',
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fa_date (founder_user_id, date),
    CONSTRAINT fk_fa_user FOREIGN KEY (founder_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- DAILY REPORTS
-- ------------------------------------------------------------

CREATE TABLE daily_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    work_completed TEXT DEFAULT NULL,
    tasks_worked_on TEXT DEFAULT NULL,
    pending_work TEXT DEFAULT NULL,
    blockers TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dr_user_date (user_id, report_date),
    CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- LEAVE MANAGEMENT
-- ------------------------------------------------------------

CREATE TABLE leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    decided_by INT UNSIGNED DEFAULT NULL,
    decided_at DATETIME DEFAULT NULL,
    decision_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lr_user (user_id),
    KEY idx_lr_status (status),
    CONSTRAINT fk_lr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) DEFAULT NULL,
    related_type VARCHAR(20) DEFAULT NULL,
    related_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- AUDIT LOG
-- ------------------------------------------------------------

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- SESSIONS (DB-backed for shared hosting reliability)
-- ------------------------------------------------------------

CREATE TABLE app_settings (
    `key` VARCHAR(80) PRIMARY KEY,
    `value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED: default statuses, services, permissions, founder role
-- ============================================================

INSERT INTO lead_statuses (name, slug, color, sort_order, is_won, is_lost, is_default) VALUES
('New', 'new', '#0d6efd', 1, 0, 0, 1),
('Contacted', 'contacted', '#6f42c1', 2, 0, 0, 0),
('Interested', 'interested', '#20c997', 3, 0, 0, 0),
('Follow-up', 'follow-up', '#fd7e14', 4, 0, 0, 0),
('Negotiation', 'negotiation', '#ffc107', 5, 0, 0, 0),
('Won', 'won', '#198754', 6, 1, 0, 0),
('Lost', 'lost', '#dc3545', 7, 0, 1, 0);

INSERT INTO services (name, slug, unit_label) VALUES
('Social Media Management', 'social-media', 'posts'),
('Video Editing', 'video-editing', 'videos'),
('Videography', 'videography', 'shoots'),
('Graphic Design', 'graphic-design', 'designs'),
('Content Writing', 'content-writing', 'blogs'),
('Performance Marketing', 'performance-marketing', 'ads'),
('SEO', 'seo', 'tasks'),
('Web Development', 'web-development', 'tasks'),
('Photography', 'photography', 'shoots');

INSERT INTO permissions (slug, name, `group`) VALUES
('leads.view', 'View own leads', 'leads'),
('leads.view_all', 'View all leads', 'leads'),
('leads.create', 'Create leads', 'leads'),
('leads.edit', 'Edit leads', 'leads'),
('leads.delete', 'Delete leads', 'leads'),
('leads.assign', 'Assign / reassign leads', 'leads'),
('leads.import', 'Import leads (CSV)', 'leads'),
('leads.export', 'Export leads', 'leads'),
('clients.view', 'View assigned client work', 'clients'),
('clients.view_all', 'View all clients', 'clients'),
('clients.create', 'Create clients', 'clients'),
('clients.edit', 'Edit clients', 'clients'),
('clients.delete', 'Delete clients', 'clients'),
('clients.manage_services', 'Manage client services / requirements', 'clients'),
('clients.assign', 'Assign client work', 'clients'),
('tasks.view', 'View own tasks', 'tasks'),
('tasks.view_all', 'View all tasks', 'tasks'),
('tasks.create', 'Create tasks', 'tasks'),
('tasks.edit', 'Edit tasks', 'tasks'),
('tasks.delete', 'Delete tasks', 'tasks'),
('tasks.assign', 'Assign / reassign tasks', 'tasks'),
('users.manage', 'Manage users', 'admin'),
('roles.manage', 'Manage roles & permissions', 'admin'),
('teams.manage', 'Manage teams', 'admin'),
('reports.view_team', 'View team daily reports', 'reports'),
('reports.view_all', 'View all daily reports', 'reports'),
('leave.approve_team', 'Approve/reject team leave', 'leave'),
('leave.approve_all', 'Approve/reject all leave', 'leave'),
('calendar.view_all', 'View all calendars', 'calendar'),
('availability.manage', 'Manage founder availability', 'calendar'),
('audit.view', 'View audit log', 'admin'),
('workload.view_team', 'View team workload', 'reports'),
('workload.view_all', 'View all workload', 'reports');

INSERT INTO roles (name, slug, description, is_system) VALUES
('Founder', 'founder', 'Highest level access. Full control of the agency CRM.', 1),
('Manager', 'manager', 'Manages one or more teams and their work.', 1),
('Sales', 'sales', 'Handles leads and follow-ups.', 0),
('Editor', 'editor', 'Handles video/content editing work.', 0),
('Videographer', 'videographer', 'Handles shoots.', 0),
('Social Media', 'social-media', 'Handles social content calendar.', 0),
('Content', 'content', 'Handles blogs / written content.', 0),
('Designer', 'designer', 'Handles graphic design work.', 0),
('Performance Marketing', 'performance-marketing', 'Handles paid ads.', 0),
('SEO', 'seo', 'Handles SEO work.', 0),
('Developer', 'developer', 'Handles web development work.', 0),
('Photographer', 'photographer', 'Handles photography shoots.', 0);

-- Founder gets every permission
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE slug='founder'), id FROM permissions;

-- Manager gets team-scoped permissions (no view_all/global admin)
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE slug='manager'), id FROM permissions
WHERE slug IN (
 'leads.view','leads.create','leads.edit','leads.import','leads.export',
 'clients.view','clients.edit','clients.manage_services','clients.assign',
 'tasks.view','tasks.create','tasks.edit','tasks.assign',
 'reports.view_team','leave.approve_team','workload.view_team','calendar.view_all'
);

-- Sales role
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE slug='sales'), id FROM permissions
WHERE slug IN ('leads.view','leads.create','leads.edit','tasks.view');

-- Generic execution roles (editor, videographer, social-media, content, designer, performance-marketing, seo, developer, photographer)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('tasks.view','clients.view')
WHERE r.slug IN ('editor','videographer','social-media','content','designer','performance-marketing','seo','developer','photographer');

INSERT INTO app_settings (`key`, `value`) VALUES ('installed', '0');
INSERT INTO app_settings (`key`, `value`) VALUES ('app_name', 'Agency CRM');
INSERT INTO app_settings (`key`, `value`) VALUES ('timezone', 'Asia/Kolkata');
