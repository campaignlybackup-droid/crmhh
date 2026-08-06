-- Phase 10 Migration Script

ALTER TABLE invoices ADD COLUMN payment_date DATE DEFAULT NULL;
