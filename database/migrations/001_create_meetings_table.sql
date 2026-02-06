-- Migration: Create meetings table
-- Description: Adds meetings functionality linked to leads

CREATE TABLE IF NOT EXISTS `meetings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `org_id` INT(11) NOT NULL,
    `lead_id` INT(11) NOT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `meeting_date` DATETIME NOT NULL,
    `duration` INT(11) DEFAULT 30 COMMENT 'Duration in minutes',
    `mode` ENUM('in_person', 'phone', 'video', 'other') DEFAULT 'in_person',
    `notes` TEXT DEFAULT NULL,
    `outcome` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    KEY `idx_org_id` (`org_id`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_meeting_date` (`meeting_date`),
    
    CONSTRAINT `fk_meetings_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_meetings_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_meetings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
