-- Add field visibility management
-- This allows hiding/showing both custom and mandatory fields

CREATE TABLE IF NOT EXISTS field_visibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('standard', 'custom') DEFAULT 'custom',
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_field (org_id, field_name),
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
