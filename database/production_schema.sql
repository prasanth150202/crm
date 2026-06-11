-- CRM Production Schema & Default Data
-- Generated: 2026-02-23 14:52:00

SET FOREIGN_KEY_CHECKS = 0;

-- Table structure for table `organizations` 
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `plan_tier` varchar(50) DEFAULT 'free',
  `status` enum('active','suspended') DEFAULT 'active',
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `api_key` varchar(64) DEFAULT NULL,
  `current_plan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_organizations_current_plan_id` (`current_plan_id`),
  CONSTRAINT `fk_organizations_current_plan_id` FOREIGN KEY (`current_plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `organization_members` 
DROP TABLE IF EXISTS `organization_members`;
CREATE TABLE `organization_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `role` enum('owner','admin','manager','staff') NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `users` 
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','admin','manager','staff','super_admin','viewer') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_super_admin` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `user_organizations` 
DROP TABLE IF EXISTS `user_organizations`;
CREATE TABLE `user_organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `role` enum('owner','admin','manager','sales_rep') NOT NULL DEFAULT 'sales_rep',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `user_permissions` 
DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `knob_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_knob` (`user_id`,`knob_key`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_knob_key` (`knob_key`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`knob_key`) REFERENCES `feature_knobs` (`knob_key`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `plans` 
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `base_price_monthly` decimal(10,2) NOT NULL,
  `included_users` int(11) NOT NULL DEFAULT 1,
  `price_per_additional_user_monthly` decimal(10,2) DEFAULT NULL,
  `base_price_yearly` decimal(10,2) DEFAULT NULL,
  `price_per_additional_user_yearly` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `razorpay_plan_id_monthly` varchar(255) DEFAULT NULL,
  `razorpay_plan_id_yearly` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_plan_id_monthly` (`razorpay_plan_id_monthly`),
  UNIQUE KEY `razorpay_plan_id_yearly` (`razorpay_plan_id_yearly`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `subscriptions` 
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `razorpay_subscription_id` varchar(255) NOT NULL,
  `status` enum('trialing','active','halted','cancelled','past_due','completed') NOT NULL DEFAULT 'trialing',
  `billing_interval` enum('monthly','yearly') NOT NULL,
  `billed_users_count` int(11) NOT NULL DEFAULT 1,
  `trial_starts_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_subscription_id` (`razorpay_subscription_id`),
  KEY `organization_id` (`organization_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `plan_features` 
DROP TABLE IF EXISTS `plan_features`;
CREATE TABLE `plan_features` (
  `plan_id` int(11) NOT NULL,
  `knob_key` varchar(100) NOT NULL,
  PRIMARY KEY (`plan_id`,`knob_key`),
  KEY `knob_key` (`knob_key`),
  CONSTRAINT `plan_features_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plan_features_ibfk_2` FOREIGN KEY (`knob_key`) REFERENCES `feature_knobs` (`knob_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `feature_knobs` 
DROP TABLE IF EXISTS `feature_knobs`;
CREATE TABLE `feature_knobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `knob_key` varchar(100) NOT NULL,
  `knob_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('leads','users','reports','settings','system') DEFAULT 'leads',
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `knob_key` (`knob_key`),
  KEY `idx_category` (`category`),
  KEY `idx_knob_key` (`knob_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `role_permissions` 
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('owner','admin','manager','staff') NOT NULL,
  `knob_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `org_id` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_knob_org` (`role`,`knob_key`,`org_id`),
  KEY `idx_role` (`role`),
  KEY `idx_knob_key` (`knob_key`),
  KEY `idx_org_id` (`org_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`knob_key`) REFERENCES `feature_knobs` (`knob_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `leads` 
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `pipeline_id` int(11) DEFAULT NULL,
  `stage_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `lead_value` decimal(10,2) DEFAULT 0.00,
  `source` varchar(100) DEFAULT NULL,
  `custom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_contacted_at` timestamp NULL DEFAULT NULL,
  `last_contacted_at` timestamp NULL DEFAULT NULL,
  `next_followup_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_email` (`org_id`,`email`),
  KEY `org_id` (`org_id`),
  KEY `owner_id` (`owner_id`),
  KEY `pipeline_id` (`pipeline_id`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `lead_assignments` 
DROP TABLE IF EXISTS `lead_assignments`;
CREATE TABLE `lead_assignments` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `assigned_to_user_id` int(11) NOT NULL,
  `assigned_by_user_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `pipelines` 
DROP TABLE IF EXISTS `pipelines`;
CREATE TABLE `pipelines` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `stages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of stage objects {id, name, color}' CHECK (json_valid(`stages`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `custom_fields` 
DROP TABLE IF EXISTS `custom_fields`;
CREATE TABLE `custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL DEFAULT 1,
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('text','textarea','date','select') DEFAULT 'text',
  `field_options` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_field` (`org_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `meetings` 
DROP TABLE IF EXISTS `meetings`;
CREATE TABLE `meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `meeting_date` datetime NOT NULL,
  `duration` int(11) DEFAULT 30 COMMENT 'Duration in minutes',
  `mode` enum('in_person','phone','video','other') DEFAULT 'in_person',
  `notes` text DEFAULT NULL,
  `outcome` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_id` (`org_id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_meeting_date` (`meeting_date`),
  CONSTRAINT `fk_meetings_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meetings_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meetings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `invitation_tokens` 
DROP TABLE IF EXISTS `invitation_tokens`;
CREATE TABLE `invitation_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of token',
  `org_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('owner','admin','manager','staff') NOT NULL DEFAULT 'staff',
  `assigned_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of feature knob_keys' CHECK (json_valid(`assigned_features`)),
  `created_by` int(11) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `used_by` int(11) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  UNIQUE KEY `unique_token_hash` (`token_hash`),
  KEY `idx_org_id` (`org_id`),
  KEY `idx_email` (`email`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_by` (`created_by`),
  KEY `fk_invitations_used_by` (`used_by`),
  KEY `fk_invitations_revoked_by` (`revoked_by`),
  CONSTRAINT `fk_invitations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invitations_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invitations_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invitations_used_by` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `dashboard_layouts` 
DROP TABLE IF EXISTS `dashboard_layouts`;
CREATE TABLE `dashboard_layouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`layout`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_org_dashboard` (`user_id`,`org_id`),
  KEY `org_id` (`org_id`),
  CONSTRAINT `dashboard_layouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_layouts_ibfk_2` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `custom_charts` 
DROP TABLE IF EXISTS `custom_charts`;
CREATE TABLE `custom_charts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `chart_type` varchar(50) NOT NULL,
  `data_field` varchar(100) NOT NULL,
  `aggregation` varchar(50) NOT NULL,
  `x_axis` varchar(100) NOT NULL,
  `color_scheme` varchar(50) DEFAULT 'default',
  `chart_sort` varchar(50) DEFAULT 'default',
  `show_total` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `global` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_order` (`org_id`,`sort_order`),
  CONSTRAINT `custom_charts_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `automation_workflows` 
DROP TABLE IF EXISTS `automation_workflows`;
CREATE TABLE `automation_workflows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `scope` enum('organization','user') DEFAULT 'organization',
  `created_by` int(11) NOT NULL,
  `is_shared` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_org_active` (`org_id`,`is_active`),
  KEY `idx_scope_owner` (`scope`,`created_by`),
  CONSTRAINT `automation_workflows_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `automation_workflows_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `automation_triggers` 
DROP TABLE IF EXISTS `automation_triggers`;
CREATE TABLE `automation_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `trigger_type` enum('lead_created','lead_stage_changed','lead_assigned','field_changed','webhook_received','scheduled') NOT NULL,
  `trigger_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_workflow` (`workflow_id`),
  KEY `idx_trigger_type` (`trigger_type`),
  CONSTRAINT `automation_triggers_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `automation_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `automation_actions` 
DROP TABLE IF EXISTS `automation_actions`;
CREATE TABLE `automation_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `action_type` enum('webhook','zingbot','assign_user','update_field','send_notification','add_note') NOT NULL,
  `action_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_config`)),
  `execution_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_workflow_order` (`workflow_id`,`execution_order`),
  CONSTRAINT `automation_actions_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `automation_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `automation_execution_logs` 
DROP TABLE IF EXISTS `automation_execution_logs`;
CREATE TABLE `automation_execution_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `trigger_type` varchar(50) DEFAULT NULL,
  `status` enum('success','failed','partial') NOT NULL,
  `execution_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`execution_data`)),
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_workflow` (`workflow_id`),
  KEY `idx_lead` (`lead_id`),
  KEY `idx_executed_at` (`executed_at`),
  CONSTRAINT `automation_execution_logs_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `automation_workflows` (`id`) ON DELETE CASCADE,
  CONSTRAINT `automation_execution_logs_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `zingbot_settings` 
DROP TABLE IF EXISTS `zingbot_settings`;
CREATE TABLE `zingbot_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `api_key` varchar(500) DEFAULT NULL,
  `api_endpoint` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_id` (`org_id`),
  CONSTRAINT `zingbot_settings_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `webhook_connections` 
DROP TABLE IF EXISTS `webhook_connections`;
CREATE TABLE `webhook_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `conn_id` varchar(32) NOT NULL,
  `conn_id_num` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_conn` (`org_id`,`conn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `webhook_field_mappings` 
DROP TABLE IF EXISTS `webhook_field_mappings`;
CREATE TABLE `webhook_field_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `conn_id` varchar(32) NOT NULL,
  `mapping` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`mapping`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_conn` (`org_id`,`conn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `webhook_logs` 
DROP TABLE IF EXISTS `webhook_logs`;
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL,
  `webhook_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `webhook_payload_logs` 
DROP TABLE IF EXISTS `webhook_payload_logs`;
CREATE TABLE `webhook_payload_logs` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `conn_id` varchar(64) NOT NULL,
  `payload` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `webhooks` 
DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`events`)),
  `secret` varchar(32) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `activities` 
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('note','call','email','meeting','status_change','task') NOT NULL,
  `content` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `org_id` (`org_id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `activity_log` 
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `org_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT 'lead',
  `entity_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_org_id` (`org_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `api_sessions` 
DROP TABLE IF EXISTS `api_sessions`;
CREATE TABLE `api_sessions` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `org_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `org_id` (`org_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `teams` 
DROP TABLE IF EXISTS `teams`;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `manager_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `team_members` 
DROP TABLE IF EXISTS `team_members`;
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Default Data Seeding --

-- Dumping data for table `plans` 
INSERT INTO `plans` (`id`, `name`, `description`, `features`, `base_price_monthly`, `included_users`, `price_per_additional_user_monthly`, `base_price_yearly`, `price_per_additional_user_yearly`, `currency`, `razorpay_plan_id_monthly`, `razorpay_plan_id_yearly`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Basic Plan', 'Essential tools for small teams and individuals.', '[\"Up to 3 active users (300 INR\\/month per additional user)\",\"Standard lead tracking and management\",\"Basic reports\",\"Email support\"]', '1999.00', '3', '300.00', '19999.00', '250.00', 'INR', 'plan_S9bwvzagWDup6L', 'plan_S9bwvzagWDup6L', '1', '2026-01-27 13:48:54', '2026-01-29 13:02:03');
INSERT INTO `plans` (`id`, `name`, `description`, `features`, `base_price_monthly`, `included_users`, `price_per_additional_user_monthly`, `base_price_yearly`, `price_per_additional_user_yearly`, `currency`, `razorpay_plan_id_monthly`, `razorpay_plan_id_yearly`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'Professional Plan', 'Advanced features for growing teams.', '[\"Up to 5 active users (500 INR\\/month per additional user)\",\"All Basic Plan features\",\"Customizable lead fields\",\"Advanced reporting and analytics\",\"Limited lead workflow automation\",\"Standard third-party integrations\",\"Priority email and chat support\"]', '4999.00', '5', '500.00', '49999.00', '400.00', 'INR', NULL, NULL, '1', '2026-01-27 13:48:54', '2026-01-27 13:48:54');
INSERT INTO `plans` (`id`, `name`, `description`, `features`, `base_price_monthly`, `included_users`, `price_per_additional_user_monthly`, `base_price_yearly`, `price_per_additional_user_yearly`, `currency`, `razorpay_plan_id_monthly`, `razorpay_plan_id_yearly`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Enterprise Plan', 'Full power and extensive customization for large organizations (custom pricing).', '[\"Custom pricing\",\"Unlimited active users (billed per user based on custom quote)\",\"All Professional Plan features\",\"Full automation suite\",\"Premium integrations (Salesforce, HubSpot)\",\"API access\",\"Dedicated account manager\",\"24\\/7 Phone, email, and chat support\"]', '0.00', '0', '0.00', '0.00', '0.00', 'INR', NULL, NULL, '1', '2026-01-27 13:48:54', '2026-01-27 13:48:54');

-- Dumping data for table `feature_knobs` 
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('1', 'view_unassigned_leads', 'View Unassigned Leads', 'Can view leads that are not assigned to anyone', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('2', 'view_own_assigned_leads', 'View Own Assigned Leads', 'Can view leads assigned to themselves', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('3', 'view_all_assigned_leads', 'View All Assigned Leads', 'Can view all leads regardless of assignment', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('4', 'assign_leads', 'Assign Leads', 'Can assign leads to team members', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('5', 'reassign_leads', 'Reassign Leads', 'Can reassign leads from one user to another', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('6', 'update_lead_status', 'Update Lead Status', 'Can change lead status/stage', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('7', 'add_lead_notes', 'Add Notes to Leads', 'Can add notes and comments to leads', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('8', 'delete_leads', 'Delete Leads', 'Can permanently delete leads', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('9', 'import_leads', 'Import Leads', 'Can import leads from CSV files', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('10', 'export_leads', 'Export Leads', 'Can export leads to CSV', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('11', 'create_leads', 'Create Leads', 'Can create new leads manually', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('12', 'edit_own_leads', 'Edit Own Assigned Leads', 'Can edit leads assigned to themselves', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('13', 'edit_all_leads', 'Edit All Leads', 'Can edit any lead in the organization', 'leads', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('14', 'view_reports', 'View Reports', 'Can access the reports dashboard', 'reports', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('15', 'export_reports', 'Export Reports', 'Can export report data', 'reports', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('16', 'create_custom_reports', 'Create Custom Reports', 'Can build custom report charts', 'reports', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('17', 'manage_users', 'Manage Users', 'Can create, edit, and delete users', 'users', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('18', 'manage_roles', 'Manage Roles', 'Can modify role permissions', 'users', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('19', 'view_user_list', 'View User List', 'Can see list of all users in organization', 'users', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('20', 'reset_user_passwords', 'Reset User Passwords', 'Can reset passwords for other users', 'users', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('21', 'access_settings', 'Access Settings', 'Can access organization settings', 'settings', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('22', 'access_integrations', 'Access Integrations', 'Can manage external integrations', 'settings', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('23', 'access_automations', 'Access Automations', 'View the automations page and see workflows', 'settings', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('24', 'manage_custom_fields', 'Manage Custom Fields', 'Can create and modify custom fields', 'settings', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('25', 'manage_organization', 'Manage Organization', 'Can modify organization-level settings', 'settings', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('26', 'view_activity_log', 'View Activity Log', 'Can view system activity and audit logs', 'system', '1', '2025-12-27 17:36:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('53', 'view_meetings', 'View Meetings', 'Can access the meetings module and view meetings', '', '1', '2026-01-21 12:24:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('54', 'create_meetings', 'Create Meetings', 'Can create new meetings for leads', '', '1', '2026-01-21 12:24:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('55', 'edit_own_meetings', 'Edit Own Meetings', 'Can edit meetings they created', '', '1', '2026-01-21 12:24:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('56', 'edit_all_meetings', 'Edit All Meetings', 'Can edit any meeting in the organization', '', '1', '2026-01-21 12:24:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('57', 'delete_meetings', 'Delete Meetings', 'Can delete meetings', '', '1', '2026-01-21 12:24:34');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('58', 'create_automations', 'Create Automations', 'Create personal automation workflows', 'settings', '1', '2026-01-24 12:10:51');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('59', 'create_org_automations', 'Create Organization Automations', 'Create organization-wide automation workflows (admin)', 'settings', '1', '2026-01-24 12:10:51');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('60', 'edit_all_automations', 'Edit All Automations', 'Edit any automation workflow in the organization', 'settings', '1', '2026-01-24 12:10:51');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('61', 'delete_automations', 'Delete Automations', 'Delete automation workflows', 'settings', '1', '2026-01-24 12:10:51');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('62', 'manage_zingbot_settings', 'Manage Zingbot Settings', 'Configure Zingbot API keys and settings', 'settings', '1', '2026-01-24 12:10:51');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('63', 'manage_leads', '', 'Manage Leads', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('64', 'basic_user_management', '', 'Basic User Management', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('65', 'organization_profile', '', 'Organization Profile', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('66', 'basic_reports', '', 'Basic Reports', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('67', 'email_support', '', 'Email Support', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('68', 'dashboard_basic', '', 'Basic Dashboard', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('70', 'advanced_user_management', '', 'Advanced User Management', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('71', 'custom_lead_fields', '', 'Custom Lead Fields', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('72', 'advanced_reports', '', 'Advanced Reports', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('73', 'basic_automations', '', 'Basic Automations', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('74', 'standard_integrations', '', 'Standard Integrations', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('75', 'chat_support', '', 'Chat Support', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('76', 'dashboard_advanced', '', 'Advanced Dashboard', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('77', 'audit_log_view', '', 'View Audit Logs', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('78', 'unlimited_users', '', 'Unlimited Users', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('79', 'advanced_automations', '', 'Advanced Automations', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('80', 'external_webhooks', '', 'External Webhooks', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('81', 'premium_integrations', '', 'Premium Integrations', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('82', 'api_access', '', 'API Access', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('83', 'dedicated_account_manager', '', 'Dedicated Account Manager', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('84', 'enhanced_security', '', 'Enhanced Security', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('85', 'audit_log_full', '', 'Full Audit Logs', 'leads', '0', '2026-02-09 17:23:27');
INSERT INTO `feature_knobs` (`id`, `knob_key`, `knob_name`, `description`, `category`, `is_system`, `created_at`) VALUES ('86', 'phone_support', '', 'Phone Support', 'leads', '0', '2026-02-09 17:23:27');

-- Dumping data for table `plan_features` 
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'add_lead_notes');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'assign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'basic_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'basic_user_management');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'create_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'create_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'dashboard_basic');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'delete_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'edit_all_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'edit_own_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'edit_own_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'email_support');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'manage_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'manage_users');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'organization_profile');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'reassign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'update_lead_status');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'view_all_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'view_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'view_own_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'view_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('1', 'view_unassigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'access_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'access_integrations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'add_lead_notes');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'advanced_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'assign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'basic_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'basic_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'basic_user_management');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'chat_support');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'create_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'create_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'create_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'dashboard_advanced');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'dashboard_basic');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'delete_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'delete_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'edit_all_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'edit_all_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'edit_own_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'edit_own_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'export_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'export_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'import_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'manage_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'manage_users');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'organization_profile');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'reassign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'reset_user_passwords');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'standard_integrations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'update_lead_status');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_all_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_own_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_unassigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('2', 'view_user_list');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'access_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'access_integrations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'access_settings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'add_lead_notes');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'advanced_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'advanced_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'advanced_user_management');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'api_access');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'assign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'audit_log_full');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'audit_log_view');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'basic_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'basic_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'basic_user_management');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'chat_support');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'create_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'create_custom_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'create_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'create_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'create_org_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'dashboard_advanced');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'dashboard_basic');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'dedicated_account_manager');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'delete_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'delete_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'delete_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'edit_all_automations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'edit_all_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'edit_all_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'edit_own_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'edit_own_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'email_support');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'enhanced_security');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'export_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'export_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'external_webhooks');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'import_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'manage_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'manage_organization');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'manage_roles');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'manage_users');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'organization_profile');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'phone_support');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'premium_integrations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'reassign_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'reset_user_passwords');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'standard_integrations');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'unlimited_users');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'update_lead_status');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_activity_log');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_all_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_meetings');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_own_assigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_reports');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_unassigned_leads');
INSERT INTO `plan_features` (`plan_id`, `knob_key`) VALUES ('3', 'view_user_list');

-- Dumping data for table `role_permissions` 
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('1', 'owner', 'access_automations', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('2', 'owner', 'access_integrations', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('3', 'owner', 'access_settings', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('4', 'owner', 'add_lead_notes', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('5', 'owner', 'assign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('6', 'owner', 'create_custom_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('7', 'owner', 'create_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('8', 'owner', 'delete_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('9', 'owner', 'edit_all_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('10', 'owner', 'edit_own_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('11', 'owner', 'export_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('12', 'owner', 'export_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('13', 'owner', 'import_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('14', 'owner', 'manage_custom_fields', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('15', 'owner', 'manage_organization', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('16', 'owner', 'manage_roles', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('17', 'owner', 'manage_users', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('18', 'owner', 'reassign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('19', 'owner', 'reset_user_passwords', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('20', 'owner', 'update_lead_status', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('21', 'owner', 'view_activity_log', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('22', 'owner', 'view_all_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('23', 'owner', 'view_own_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('24', 'owner', 'view_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('25', 'owner', 'view_unassigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('26', 'owner', 'view_user_list', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('32', 'admin', 'access_automations', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('33', 'admin', 'access_integrations', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('34', 'admin', 'access_settings', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('35', 'admin', 'add_lead_notes', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('36', 'admin', 'assign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('37', 'admin', 'create_custom_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('38', 'admin', 'create_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('39', 'admin', 'delete_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('40', 'admin', 'edit_all_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('41', 'admin', 'edit_own_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('42', 'admin', 'export_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('43', 'admin', 'export_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('44', 'admin', 'import_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('45', 'admin', 'manage_custom_fields', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('46', 'admin', 'manage_roles', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('47', 'admin', 'manage_users', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('48', 'admin', 'reassign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('49', 'admin', 'reset_user_passwords', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('50', 'admin', 'update_lead_status', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('51', 'admin', 'view_activity_log', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('52', 'admin', 'view_all_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('53', 'admin', 'view_own_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('54', 'admin', 'view_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('55', 'admin', 'view_unassigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('56', 'admin', 'view_user_list', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('63', 'manager', 'add_lead_notes', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('64', 'manager', 'assign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('65', 'manager', 'create_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('66', 'manager', 'edit_all_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('67', 'manager', 'export_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('68', 'manager', 'export_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('69', 'manager', 'import_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('70', 'manager', 'reassign_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('71', 'manager', 'update_lead_status', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('72', 'manager', 'view_activity_log', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('73', 'manager', 'view_all_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('74', 'manager', 'view_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('75', 'manager', 'view_unassigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('76', 'manager', 'view_user_list', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('78', 'staff', 'add_lead_notes', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('79', 'staff', 'create_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('80', 'staff', 'edit_own_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('81', 'staff', 'update_lead_status', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('82', 'staff', 'view_own_assigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('83', 'staff', 'view_reports', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('84', 'staff', 'view_unassigned_leads', '1', NULL, NULL, '2025-12-27 17:36:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('85', 'owner', 'access_automations', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('86', 'owner', 'access_integrations', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('87', 'owner', 'access_settings', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('88', 'owner', 'add_lead_notes', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('89', 'owner', 'assign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('90', 'owner', 'create_custom_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('91', 'owner', 'create_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('92', 'owner', 'delete_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('93', 'owner', 'edit_all_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('94', 'owner', 'edit_own_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('95', 'owner', 'export_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('96', 'owner', 'export_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('97', 'owner', 'import_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('98', 'owner', 'manage_custom_fields', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('99', 'owner', 'manage_organization', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('100', 'owner', 'manage_roles', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('101', 'owner', 'manage_users', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('102', 'owner', 'reassign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('103', 'owner', 'reset_user_passwords', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('104', 'owner', 'update_lead_status', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('105', 'owner', 'view_activity_log', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('106', 'owner', 'view_all_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('107', 'owner', 'view_own_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('108', 'owner', 'view_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('109', 'owner', 'view_unassigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('110', 'owner', 'view_user_list', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('116', 'admin', 'access_automations', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('117', 'admin', 'access_integrations', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('118', 'admin', 'access_settings', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('119', 'admin', 'add_lead_notes', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('120', 'admin', 'assign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('121', 'admin', 'create_custom_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('122', 'admin', 'create_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('123', 'admin', 'delete_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('124', 'admin', 'edit_all_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('125', 'admin', 'edit_own_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('126', 'admin', 'export_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('127', 'admin', 'export_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('128', 'admin', 'import_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('129', 'admin', 'manage_custom_fields', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('130', 'admin', 'manage_roles', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('131', 'admin', 'manage_users', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('132', 'admin', 'reassign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('133', 'admin', 'reset_user_passwords', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('134', 'admin', 'update_lead_status', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('135', 'admin', 'view_activity_log', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('136', 'admin', 'view_all_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('137', 'admin', 'view_own_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('138', 'admin', 'view_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('139', 'admin', 'view_unassigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('140', 'admin', 'view_user_list', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('147', 'manager', 'add_lead_notes', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('148', 'manager', 'assign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('149', 'manager', 'create_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('150', 'manager', 'edit_all_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('151', 'manager', 'export_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('152', 'manager', 'export_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('153', 'manager', 'import_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('154', 'manager', 'reassign_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('155', 'manager', 'update_lead_status', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('156', 'manager', 'view_activity_log', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('157', 'manager', 'view_all_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('158', 'manager', 'view_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('159', 'manager', 'view_unassigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('160', 'manager', 'view_user_list', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('162', 'staff', 'add_lead_notes', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('163', 'staff', 'create_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('164', 'staff', 'edit_own_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('165', 'staff', 'update_lead_status', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('166', 'staff', 'view_own_assigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('167', 'staff', 'view_reports', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('168', 'staff', 'view_unassigned_leads', '1', NULL, NULL, '2026-01-19 12:16:01');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('169', 'owner', 'view_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('170', 'owner', 'create_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('171', 'owner', 'edit_own_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('172', 'owner', 'edit_all_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('173', 'owner', 'delete_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('174', 'admin', 'view_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('175', 'admin', 'create_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('176', 'admin', 'edit_own_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('177', 'admin', 'edit_all_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('178', 'admin', 'delete_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('179', 'manager', 'view_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('180', 'manager', 'create_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('181', 'manager', 'edit_own_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('182', 'manager', 'edit_all_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('183', 'staff', 'view_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('184', 'staff', 'create_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('185', 'staff', 'edit_own_meetings', '1', NULL, NULL, '2026-01-21 12:24:34');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('186', 'owner', 'access_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('187', 'owner', 'create_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('188', 'owner', 'create_org_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('189', 'owner', 'delete_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('190', 'owner', 'edit_all_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('191', 'owner', 'manage_zingbot_settings', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('193', 'admin', 'access_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('194', 'admin', 'create_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('195', 'admin', 'create_org_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('196', 'admin', 'delete_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('197', 'admin', 'edit_all_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('198', 'admin', 'manage_zingbot_settings', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('200', 'manager', 'access_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('201', 'manager', 'create_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('202', 'manager', 'create_org_automations', '1', NULL, NULL, '2026-01-24 12:10:51');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('203', 'staff', 'access_automations', '1', NULL, NULL, '2026-01-24 12:10:52');
INSERT INTO `role_permissions` (`id`, `role`, `knob_key`, `is_enabled`, `org_id`, `updated_by`, `updated_at`) VALUES ('204', 'staff', 'create_automations', '1', NULL, NULL, '2026-01-24 12:10:52');

-- Table structure for table `site_leads`
DROP TABLE IF EXISTS `site_leads`;
CREATE TABLE `site_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `plan_tag` varchar(100) DEFAULT NULL COMMENT 'e.g. Enterprise Plan',
  `status` enum('new','contacted','converted','ignored') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
