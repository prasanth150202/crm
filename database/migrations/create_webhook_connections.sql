-- Migration: Create webhook_connections table for persistent webhook connection management
CREATE TABLE IF NOT EXISTS webhook_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    conn_id VARCHAR(32) NOT NULL,
    conn_id_num INT NOT NULL,
    name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_conn (org_id, conn_id)
);
