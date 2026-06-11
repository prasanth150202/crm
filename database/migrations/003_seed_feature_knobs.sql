-- Phase 8: Feature Knob System - Seed Feature Knobs
-- Migration 003: Seed initial feature knobs

USE crm;

-- Insert all feature knobs based on CRM requirements
INSERT INTO feature_knobs (knob_key, knob_name, description, category, is_system) VALUES
-- Lead Management Knobs
('view_unassigned_leads', 'View Unassigned Leads', 'Can view leads that are not assigned to anyone', 'leads', TRUE),
('view_own_assigned_leads', 'View Own Assigned Leads', 'Can view leads assigned to themselves', 'leads', TRUE),
('view_all_assigned_leads', 'View All Assigned Leads', 'Can view all leads regardless of assignment', 'leads', TRUE),
('assign_leads', 'Assign Leads', 'Can assign leads to team members', 'leads', TRUE),
('reassign_leads', 'Reassign Leads', 'Can reassign leads from one user to another', 'leads', TRUE),
('update_lead_status', 'Update Lead Status', 'Can change lead status/stage', 'leads', TRUE),
('add_lead_notes', 'Add Notes to Leads', 'Can add notes and comments to leads', 'leads', TRUE),
('delete_leads', 'Delete Leads', 'Can permanently delete leads', 'leads', TRUE),
('import_leads', 'Import Leads', 'Can import leads from CSV files', 'leads', TRUE),
('export_leads', 'Export Leads', 'Can export leads to CSV', 'leads', TRUE),
('create_leads', 'Create Leads', 'Can create new leads manually', 'leads', TRUE),
('edit_own_leads', 'Edit Own Assigned Leads', 'Can edit leads assigned to themselves', 'leads', TRUE),
('edit_all_leads', 'Edit All Leads', 'Can edit any lead in the organization', 'leads', TRUE),

-- Reports & Analytics Knobs
('view_reports', 'View Reports', 'Can access the reports dashboard', 'reports', TRUE),
('export_reports', 'Export Reports', 'Can export report data', 'reports', TRUE),
('create_custom_reports', 'Create Custom Reports', 'Can build custom report charts', 'reports', TRUE),

-- User Management Knobs
('manage_users', 'Manage Users', 'Can create, edit, and delete users', 'users', TRUE),
('manage_roles', 'Manage Roles', 'Can modify role permissions', 'users', TRUE),
('view_user_list', 'View User List', 'Can see list of all users in organization', 'users', TRUE),
('reset_user_passwords', 'Reset User Passwords', 'Can reset passwords for other users', 'users', TRUE),

-- Settings & Configuration Knobs
('access_settings', 'Access Settings', 'Can access organization settings', 'settings', TRUE),
('access_integrations', 'Access Integrations', 'Can manage external integrations', 'settings', TRUE),
('access_automations', 'Access Automations', 'Can configure automation rules', 'settings', TRUE),
('manage_custom_fields', 'Manage Custom Fields', 'Can create and modify custom fields', 'settings', TRUE),
('manage_organization', 'Manage Organization', 'Can modify organization-level settings', 'settings', TRUE),

-- Activity & Audit Knobs
('view_activity_log', 'View Activity Log', 'Can view system activity and audit logs', 'system', TRUE);

-- Log migration
SELECT CONCAT('Migration 003: ', COUNT(*), ' feature knobs seeded successfully') as status FROM feature_knobs;
