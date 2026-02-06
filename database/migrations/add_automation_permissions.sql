-- Add automation-related feature knobs (permissions)

USE crm;

-- Insert automation feature knobs
INSERT INTO feature_knobs (knob_key, knob_name, description, category, is_system) 
VALUES 
('access_automations', 'Access Automations', 'View the automations page and see workflows', 'settings', 1),
('create_automations', 'Create Automations', 'Create personal automation workflows', 'settings', 1),
('create_org_automations', 'Create Organization Automations', 'Create organization-wide automation workflows (admin)', 'settings', 1),
('edit_all_automations', 'Edit All Automations', 'Edit any automation workflow in the organization', 'settings', 1),
('delete_automations', 'Delete Automations', 'Delete automation workflows', 'settings', 1),
('manage_zingbot_settings', 'Manage Zingbot Settings', 'Configure Zingbot API keys and settings', 'settings', 1)
ON DUPLICATE KEY UPDATE 
    knob_name = VALUES(knob_name),
    description = VALUES(description);

-- Grant permissions to owner role
INSERT INTO role_permissions (role, knob_key, is_enabled) 
SELECT 'owner', knob_key, 1
FROM feature_knobs 
WHERE knob_key IN ('access_automations', 'create_automations', 'create_org_automations', 'edit_all_automations', 'delete_automations', 'manage_zingbot_settings')
ON DUPLICATE KEY UPDATE is_enabled = 1;

-- Grant permissions to admin role
INSERT INTO role_permissions (role, knob_key, is_enabled) 
SELECT 'admin', knob_key, 1
FROM feature_knobs 
WHERE knob_key IN ('access_automations', 'create_automations', 'create_org_automations', 'edit_all_automations', 'delete_automations', 'manage_zingbot_settings')
ON DUPLICATE KEY UPDATE is_enabled = 1;

-- Grant permissions to manager role
INSERT INTO role_permissions (role, knob_key, is_enabled) 
SELECT 'manager', knob_key, 1
FROM feature_knobs 
WHERE knob_key IN ('access_automations', 'create_automations', 'create_org_automations')
ON DUPLICATE KEY UPDATE is_enabled = 1;

-- Grant permissions to staff role (personal automations only)
INSERT INTO role_permissions (role, knob_key, is_enabled) 
SELECT 'staff', knob_key, 1
FROM feature_knobs 
WHERE knob_key IN ('access_automations', 'create_automations')
ON DUPLICATE KEY UPDATE is_enabled = 1;

-- Log migration
SELECT 'Automation feature knobs created successfully' as status;
