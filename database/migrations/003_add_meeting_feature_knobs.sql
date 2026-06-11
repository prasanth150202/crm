-- Migration: Add meeting feature knobs
-- Description: Adds permission knobs for meetings module

INSERT INTO `feature_knobs` (`knob_key`, `knob_name`, `description`, `category`, `is_system`) VALUES
('view_meetings', 'View Meetings', 'Can access the meetings module and view meetings', 'meetings', 1),
('create_meetings', 'Create Meetings', 'Can create new meetings for leads', 'meetings', 1),
('edit_own_meetings', 'Edit Own Meetings', 'Can edit meetings they created', 'meetings', 1),
('edit_all_meetings', 'Edit All Meetings', 'Can edit any meeting in the organization', 'meetings', 1),
('delete_meetings', 'Delete Meetings', 'Can delete meetings', 'meetings', 1)
ON DUPLICATE KEY UPDATE 
    `knob_name` = VALUES(`knob_name`),
    `description` = VALUES(`description`);

-- Grant meeting permissions to owner role by default
INSERT INTO `role_permissions` (`role`, `knob_key`, `is_enabled`) VALUES
('owner', 'view_meetings', 1),
('owner', 'create_meetings', 1),
('owner', 'edit_own_meetings', 1),
('owner', 'edit_all_meetings', 1),
('owner', 'delete_meetings', 1)
ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`);

-- Grant meeting permissions to admin role by default
INSERT INTO `role_permissions` (`role`, `knob_key`, `is_enabled`) VALUES
('admin', 'view_meetings', 1),
('admin', 'create_meetings', 1),
('admin', 'edit_own_meetings', 1),
('admin', 'edit_all_meetings', 1),
('admin', 'delete_meetings', 1)
ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`);

-- Grant limited meeting permissions to manager role
INSERT INTO `role_permissions` (`role`, `knob_key`, `is_enabled`) VALUES
('manager', 'view_meetings', 1),
('manager', 'create_meetings', 1),
('manager', 'edit_own_meetings', 1),
('manager', 'edit_all_meetings', 1)
ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`);

-- Grant basic meeting permissions to staff role
INSERT INTO `role_permissions` (`role`, `knob_key`, `is_enabled`) VALUES
('staff', 'view_meetings', 1),
('staff', 'create_meetings', 1),
('staff', 'edit_own_meetings', 1)
ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`);
