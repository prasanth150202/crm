-- Phase 7.3: Multi-Organization Management
-- Database migration for multi-org support

USE crm_app;

-- Add super admin flag to users table
ALTER TABLE users ADD COLUMN is_super_admin BOOLEAN DEFAULT FALSE AFTER role;

-- Add organization status and metadata
ALTER TABLE organizations ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER name;
ALTER TABLE organizations ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_active;
ALTER TABLE organizations ADD COLUMN settings JSON AFTER created_at;

-- Create organization members table (for users who can access multiple orgs)
CREATE TABLE IF NOT EXISTS organization_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    org_id INT NOT NULL,
    role ENUM('owner', 'admin', 'manager', 'sales_rep') NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_org (user_id, org_id),
    INDEX idx_user (user_id),
    INDEX idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing users to organization_members
INSERT INTO organization_members (user_id, org_id, role, is_primary)
SELECT id, org_id, role, TRUE
FROM users
WHERE org_id IS NOT NULL;

-- Set first user of each org as super admin (you can change this manually later)
UPDATE users u
INNER JOIN (
    SELECT org_id, MIN(id) as first_user_id
    FROM users
    GROUP BY org_id
) first_users ON u.id = first_users.first_user_id
SET u.is_super_admin = TRUE;

-- Add index for faster org switching
ALTER TABLE users ADD INDEX idx_org_id (org_id);
