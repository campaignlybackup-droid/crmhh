CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'manager', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
);

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Completed', 'On Hold', 'Churned') DEFAULT 'Active',
    primary_contact VARCHAR(255),
    total_billed DECIMAL(10, 2) DEFAULT 0.00,
    monthly_payment_date VARCHAR(50) DEFAULT NULL,
    drive_folder_url VARCHAR(255),
    onboarding_date DATE,
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    status ENUM('Briefing', 'Pre-Production', 'Shoot', 'Post', 'Review', 'Delivered') DEFAULT 'Briefing',
    client_id INT,
    project_value DECIMAL(10, 2) DEFAULT 0.00,
    shoot_date DATE,
    delivery_date DATE,
    assigned_to INT,
    drive_folder_url VARCHAR(255),
    payment_status ENUM('Unpaid', '50% Received', 'Paid in Full') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status ENUM('New', 'Contacted', 'Warm', 'Proposal Sent', 'Negotiating', 'Won', 'Lost') DEFAULT 'New',
    source ENUM('Instagram DM', 'LinkedIn', 'Referral', 'WhatsApp', 'Cold Email', 'Walk-in') DEFAULT 'Walk-in',
    industry ENUM('F&B', 'Real Estate', 'Beauty', 'Automotive', 'Hospitality', 'Other') DEFAULT 'Other',
    contact_name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    instagram VARCHAR(255),
    deal_value DECIMAL(10, 2) DEFAULT 0.00,
    next_action VARCHAR(255),
    next_action_date DATE,
    notes TEXT,
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    status ENUM('To Do', 'In Progress', 'Review', 'Done') DEFAULT 'To Do',
    assigned_to INT,
    due_date DATE,
    priority ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
    project_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(100) NOT NULL,
    client_id INT,
    amount DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Unpaid', 'Paid', 'Overdue') DEFAULT 'Unpaid',
    issue_date DATE,
    due_date DATE,
    drive_link VARCHAR(255),
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE lead_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE content_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_title VARCHAR(255) NOT NULL,
    platform ENUM('IG', 'TikTok', 'LinkedIn') DEFAULT 'IG',
    status ENUM('Draft', 'Scheduled', 'Posted') DEFAULT 'Draft',
    post_date DATE,
    assigned_to INT,
    caption TEXT,
    drive_link VARCHAR(255),
    client_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert a default superadmin account (password: admin123)
-- Password hash generated for 'admin123' using PHP's password_hash function
INSERT INTO users (username, password_hash, role) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');
-- Phase 2 Migration Script

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    manager_id INT,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. Modify Users Table
ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'manager', 'employee', 'user') DEFAULT 'user';
ALTER TABLE users ADD COLUMN department_id INT DEFAULT NULL;
ALTER TABLE users ADD COLUMN reporting_manager_id INT DEFAULT NULL;
ALTER TABLE users ADD COLUMN designation VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN employment_type ENUM('Full-time', 'Part-time', 'Contract') DEFAULT NULL;
ALTER TABLE users ADD COLUMN joining_date DATE DEFAULT NULL;
ALTER TABLE users ADD COLUMN skills TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN working_hours VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- We add constraints separately to avoid syntax errors if column exists but constraint doesn't
ALTER TABLE users ADD CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE users ADD CONSTRAINT fk_user_manager FOREIGN KEY (reporting_manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Universal Components
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('Google Drive', 'PDF Link', 'Notion', 'Figma', 'Loom', 'Other') DEFAULT 'Other',
    url VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Project Workflow Engine
CREATE TABLE IF NOT EXISTS project_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    stage_name VARCHAR(255) NOT NULL,
    owner_id INT DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    status ENUM('Pending', 'In Progress', 'In Review', 'Approved') DEFAULT 'Pending',
    approver_id INT DEFAULT NULL,
    completion_date TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add soft deletes to other entities
ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE clients ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE leads ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- 5. Tasks Enhancements
ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN estimated_time DECIMAL(8,2) DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN actual_time DECIMAL(8,2) DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN reviewer_id INT DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tasks ADD CONSTRAINT fk_task_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL;

-- 6. HR Module
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in TIME DEFAULT NULL,
    clock_out TIME DEFAULT NULL,
    total_hours DECIMAL(5,2) DEFAULT NULL,
    late_marks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Manager Approved', 'Admin Approved', 'Rejected') DEFAULT 'Pending',
    manager_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS company_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Universal Approval Engine
CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    step INT DEFAULT 1,
    approver_id INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Meetings & Calendar Sync
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    project_id INT DEFAULT NULL,
    client_id INT DEFAULT NULL,
    meeting_url VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    type VARCHAR(50) NOT NULL,
    reference_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- Phase 5 Migration Script
ALTER TABLE projects MODIFY COLUMN status ENUM(
    'Onboarding', 'Creative Brief', 'Reference / Moodboard', 'Concept Approval', 
    'Pre Production', 'Production', 'Editing', 'Internal Review', 
    'Client Approval', 'Delivery', 'Case Study', 'Archive',
    -- Keeping old ones temporarily to prevent data truncation during alter
    'Briefing', 'Shoot', 'Post', 'Review', 'Delivered'
) DEFAULT 'Onboarding';
-- Phase 6 Migration Script

CREATE TABLE IF NOT EXISTS workflow_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS workflow_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    trigger_stage_name VARCHAR(100) NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    default_assignee_id INT DEFAULT NULL,
    estimated_hours INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (default_assignee_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add column using IF NOT EXISTS logic via a stored procedure approach, or just ignore error via PHP
-- In PHP we will catch duplicate column errors.
ALTER TABLE projects ADD COLUMN workflow_template_id INT DEFAULT NULL;
ALTER TABLE projects ADD FOREIGN KEY (workflow_template_id) REFERENCES workflow_templates(id) ON DELETE SET NULL;
-- Phase 8 Migration Script

ALTER TABLE leave_requests ADD COLUMN type ENUM('Sick', 'Casual', 'Paid') DEFAULT 'Casual';
-- Phase 10 Migration Script

ALTER TABLE invoices ADD COLUMN payment_date DATE DEFAULT NULL;
-- Phase 1 & 2: Architecture & Database Updates for Agency CRM

-- 1. Alter Users Table
ALTER TABLE users ADD COLUMN status ENUM('Active', 'Disabled') DEFAULT 'Active' AFTER role;

-- 2. RBAC & Teams
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    manager_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE team_members (
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Core Entities Additions
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE client_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 0,
    completed_quantity INT DEFAULT 0,
    status ENUM('Active', 'Completed', 'Cancelled') DEFAULT 'Active',
    start_date DATE,
    renewal_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Note: existing tasks table has `assigned_to` and `created_by`. 
-- We will use `task_assignments` for multiple assignees, or stick to `assigned_to` if it's 1:1. 
-- Requirements say: "A Task -> can have assigned User(s) where appropriate". We will add task_assignments to be safe.
CREATE TABLE task_assignments (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Lead Statuses (Configurable)
CREATE TABLE lead_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    order_index INT DEFAULT 0
);
-- Insert default statuses
INSERT INTO lead_statuses (name, order_index) VALUES ('New', 1), ('Contacted', 2), ('Warm', 3), ('Proposal Sent', 4), ('Negotiating', 5), ('Won', 6), ('Lost', 7);

-- 5. Productivity & HR
CREATE TABLE daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_date DATE NOT NULL,
    completed_tasks TEXT,
    pending_tasks TEXT,
    blockers TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    reviewed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Available', 'Busy', 'Meeting', 'Unavailable') DEFAULT 'Available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed basic roles
INSERT INTO roles (name, description) VALUES 
('Founder', 'Highest level access, sees and manages everything'),
('Manager', 'Manages a team and their work'),
('Sales', 'Handles leads and sales process'),
('Editor', 'Video editing role'),
('Videographer', 'Shooting and videography role'),
('Social Media', 'Social media management');

-- Map existing superadmin to Founder, manager to Manager, user to empty/basic
INSERT INTO user_roles (user_id, role_id) 
SELECT id, 1 FROM users WHERE role = 'superadmin';

INSERT INTO user_roles (user_id, role_id) 
SELECT id, 2 FROM users WHERE role = 'manager';

-- Add seed services
INSERT INTO services (name) VALUES ('Social Media'), ('Video Editing'), ('Videography'), ('Performance Marketing');
