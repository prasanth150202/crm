-- Custom Fields Table for Import Feature
CREATE TABLE IF NOT EXISTS custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'long_text', 'date', 'select') DEFAULT 'text',
    field_options TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_org_id (org_id),
    INDEX idx_field_name (field_name),
    UNIQUE KEY unique_org_field (org_id, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;