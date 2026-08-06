-- Phase 5 Migration Script
ALTER TABLE projects MODIFY COLUMN status ENUM(
    'Onboarding', 'Creative Brief', 'Reference / Moodboard', 'Concept Approval', 
    'Pre Production', 'Production', 'Editing', 'Internal Review', 
    'Client Approval', 'Delivery', 'Case Study', 'Archive',
    -- Keeping old ones temporarily to prevent data truncation during alter
    'Briefing', 'Shoot', 'Post', 'Review', 'Delivered'
) DEFAULT 'Onboarding';
