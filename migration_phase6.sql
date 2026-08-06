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
