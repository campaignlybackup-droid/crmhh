-- Phase 2 Migration Script

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    manager_id INT,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. Modify Users Table
ALTER TABLE users 
    MODIFY COLUMN role ENUM('superadmin', 'manager', 'employee', 'user') DEFAULT 'user',
    ADD COLUMN department_id INT DEFAULT NULL,
    ADD COLUMN reporting_manager_id INT DEFAULT NULL,
    ADD COLUMN designation VARCHAR(255) DEFAULT NULL,
    ADD COLUMN employment_type ENUM('Full-time', 'Part-time', 'Contract') DEFAULT NULL,
    ADD COLUMN joining_date DATE DEFAULT NULL,
    ADD COLUMN skills TEXT DEFAULT NULL,
    ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL,
    ADD COLUMN working_hours VARCHAR(255) DEFAULT NULL,
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
    ADD CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_user_manager FOREIGN KEY (reporting_manager_id) REFERENCES users(id) ON DELETE SET NULL;

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
ALTER TABLE tasks
    ADD COLUMN description TEXT DEFAULT NULL,
    ADD COLUMN estimated_time DECIMAL(8,2) DEFAULT NULL,
    ADD COLUMN actual_time DECIMAL(8,2) DEFAULT NULL,
    ADD COLUMN reviewer_id INT DEFAULT NULL,
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
    ADD CONSTRAINT fk_task_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL;

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
