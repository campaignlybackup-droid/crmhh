-- Phase 8 Migration Script

ALTER TABLE leave_requests ADD COLUMN type ENUM('Sick', 'Casual', 'Paid') DEFAULT 'Casual';
