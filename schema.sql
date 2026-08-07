CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Completed', 'On Hold', 'Churned') DEFAULT 'Active',
    primary_contact VARCHAR(255),
    total_billed DECIMAL(10, 2) DEFAULT 0.00,
    drive_folder_url VARCHAR(255),
    onboarding_date DATE,
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
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
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
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
