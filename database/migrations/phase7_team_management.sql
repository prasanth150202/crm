-- Phase 7 Database Migration Script
-- Multi-Organization Team Management
-- Date: 2025-12-08

-- 1. Enhance users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role ENUM('owner', 'admin', 'manager', 'sales_rep') DEFAULT 'sales_rep' AFTER password,
ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) AFTER email,
ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) AFTER full_name,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER avatar_url,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER is_active;

-- Update existing users to 'owner' role
UPDATE users SET role = 'owner' WHERE role IS NULL OR role = 'sales_rep';

-- 2. Create lead_assignments table
CREATE TABLE IF NOT EXISTS lead_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    assigned_to_user_id INT NOT NULL,
    assigned_by_user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lead_id (lead_id),
    INDEX idx_assigned_to (assigned_to_user_id),
    INDEX idx_assigned_at (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add assigned_to column to leads table
ALTER TABLE leads
ADD COLUMN IF NOT EXISTS assigned_to INT NULL AFTER org_id,
ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

-- 4. Create activity_log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lead_id INT NULL,
    org_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) DEFAULT 'lead',
    entity_id INT NULL,
    old_value TEXT,
    new_value TEXT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_lead_id (lead_id),
    INDEX idx_org_id (org_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    team_name VARCHAR(255) NOT NULL,
    description TEXT,
    manager_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_org_id (org_id),
    INDEX idx_manager_id (manager_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create team_members table
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_user (team_id, user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create user_permissions table
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    org_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_key, org_id),
    INDEX idx_user_id (user_id),
    INDEX idx_org_id (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Assign all existing leads to organization owner
UPDATE leads l
JOIN users u ON l.org_id = u.org_id
SET l.assigned_to = u.id
WHERE u.role = 'owner' AND l.assigned_to IS NULL;

-- 9. Log migration completion
INSERT INTO activity_log (user_id, org_id, action_type, entity_type, description)
SELECT 
    u.id,
    u.org_id,
    'system_migration',
    'database',
    'Phase 7 database migration completed - Multi-Organization Team Management'
FROM users u
WHERE u.role = 'owner'
LIMIT 1;

-- Migration complete
SELECT 'Phase 7 Database Migration Completed Successfully!' as status;
