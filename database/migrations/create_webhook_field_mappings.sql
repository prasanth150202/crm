-- Migration: Create webhook_field_mappings table for storing field mappings in DB
CREATE TABLE IF NOT EXISTS webhook_field_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    conn_id VARCHAR(32) NOT NULL,
    mapping JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_conn (org_id, conn_id)
);
