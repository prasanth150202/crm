-- Migration: Create invitation_tokens table
-- Description: Secure token-based user invitation system

CREATE TABLE IF NOT EXISTS `invitation_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of token',
    `org_id` INT(11) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `role` ENUM('owner','admin','manager','staff') NOT NULL DEFAULT 'staff',
    `assigned_features` JSON NOT NULL COMMENT 'Array of feature knob_keys',
    `created_by` INT(11) DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `used_by` INT(11) DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `revoked_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token_hash` (`token_hash`),
    KEY `idx_org_id` (`org_id`),
    KEY `idx_email` (`email`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_created_by` (`created_by`),
    
    CONSTRAINT `fk_invitations_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invitations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invitations_used_by` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invitations_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
