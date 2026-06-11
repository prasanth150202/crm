-- Phase 8: Feature Knob System - Create Feature Knobs Table
-- Migration 001: Create feature_knobs table

USE crm;

-- Create feature_knobs table to store all available system capabilities
CREATE TABLE IF NOT EXISTS feature_knobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    knob_key VARCHAR(100) UNIQUE NOT NULL,
    knob_name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('leads', 'users', 'reports', 'settings', 'system') DEFAULT 'leads',
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_knob_key (knob_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log migration
SELECT 'Migration 001: feature_knobs table created successfully' as status;
