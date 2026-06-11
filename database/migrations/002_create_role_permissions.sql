-- Phase 8: Feature Knob System - Create Role Permissions Table
-- Migration 002: Create role_permissions table

USE crm;

-- Create role_permissions table for role-to-feature mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('owner', 'admin', 'manager', 'staff') NOT NULL,
    knob_key VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    org_id INT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (knob_key) REFERENCES feature_knobs(knob_key) ON DELETE CASCADE,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_role_knob_org (role, knob_key, org_id),
    INDEX idx_role (role),
    INDEX idx_knob_key (knob_key),
    INDEX idx_org_id (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log migration
SELECT 'Migration 002: role_permissions table created successfully' as status;
