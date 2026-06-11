-- Phase 8: Feature Knob System - Migrate Existing Permissions
-- Migration 004: Migrate existing hardcoded permissions to database

USE crm;

-- Owner gets all permissions (global, not org-specific)
INSERT INTO role_permissions (role, knob_key, is_enabled, org_id)
SELECT 'owner', knob_key, TRUE, NULL FROM feature_knobs;

-- Admin gets all permissions except manage_organization (global)
INSERT INTO role_permissions (role, knob_key, is_enabled, org_id)
SELECT 'admin', knob_key, TRUE, NULL 
FROM feature_knobs 
WHERE knob_key != 'manage_organization';

-- Manager permissions (global)
INSERT INTO role_permissions (role, knob_key, is_enabled, org_id)
SELECT 'manager', knob_key, TRUE, NULL 
FROM feature_knobs 
WHERE knob_key IN (
    'view_unassigned_leads', 
    'view_all_assigned_leads', 
    'assign_leads', 
    'reassign_leads',
    'update_lead_status', 
    'add_lead_notes', 
    'import_leads', 
    'export_leads',
    'create_leads', 
    'edit_all_leads', 
    'view_reports', 
    'export_reports',
    'view_user_list', 
    'view_activity_log'
);

-- Staff permissions (minimal, global)
INSERT INTO role_permissions (role, knob_key, is_enabled, org_id)
SELECT 'staff', knob_key, TRUE, NULL 
FROM feature_knobs 
WHERE knob_key IN (
    'view_unassigned_leads', 
    'view_own_assigned_leads', 
    'update_lead_status',
    'add_lead_notes', 
    'create_leads', 
    'edit_own_leads', 
    'view_reports'
);

-- Update users table role enum to use 'staff' instead of 'sales_rep'
-- First, update existing sales_rep users to staff
UPDATE users SET role = 'staff' WHERE role = 'sales_rep';

-- Then alter the enum (this will fail gracefully if already updated)
ALTER TABLE users 
MODIFY COLUMN role ENUM('owner', 'admin', 'manager', 'staff') DEFAULT 'staff';

-- Also update organization_members if it exists
UPDATE organization_members SET role = 'staff' WHERE role = 'sales_rep';
ALTER TABLE organization_members 
MODIFY COLUMN role ENUM('owner', 'admin', 'manager', 'staff') NOT NULL;

-- Log migration
SELECT 'Migration 004: Permissions migrated and role enum updated successfully' as status;
