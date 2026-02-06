-- Automation Module Migration
-- Creates tables for automation workflows, triggers, actions, execution logs, and Zingbot settings

USE crm;

-- Main automation workflows table
CREATE TABLE IF NOT EXISTS automation_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    scope ENUM('organization', 'user') DEFAULT 'organization',
    created_by INT NOT NULL,
    is_shared BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_org_active (org_id, is_active),
    INDEX idx_scope_owner (scope, created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automation triggers - what starts the workflow
CREATE TABLE IF NOT EXISTS automation_triggers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    trigger_type ENUM('lead_created', 'lead_stage_changed', 'lead_assigned', 'field_changed', 'webhook_received', 'scheduled') NOT NULL,
    trigger_config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
    INDEX idx_workflow (workflow_id),
    INDEX idx_trigger_type (trigger_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automation actions - what the workflow does
CREATE TABLE IF NOT EXISTS automation_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    action_type ENUM('webhook', 'zingbot', 'assign_user', 'update_field', 'send_notification', 'add_note') NOT NULL,
    action_config JSON,
    execution_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
    INDEX idx_workflow_order (workflow_id, execution_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Execution logs for debugging and audit
CREATE TABLE IF NOT EXISTS automation_execution_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    lead_id INT,
    trigger_type VARCHAR(50),
    status ENUM('success', 'failed', 'partial') NOT NULL,
    execution_data JSON,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    INDEX idx_workflow (workflow_id),
    INDEX idx_lead (lead_id),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Zingbot configuration per organization
CREATE TABLE IF NOT EXISTS zingbot_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL UNIQUE,
    api_key VARCHAR(500),
    api_endpoint VARCHAR(500),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log migration
SELECT 'Automation tables created successfully' as status;
