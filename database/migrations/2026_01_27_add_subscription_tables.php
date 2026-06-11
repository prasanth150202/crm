<?php
// database/migrations/2026_01_27_add_subscription_tables.php

// This script assumes it is run within a context where $pdo is available
// or that it includes a database connection setup.

echo "Running migration: 2026_01_27_add_subscription_tables...\n";

// Ensure a PDO connection is available or attempt to establish one
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "PDO connection not found. Attempting to load config and connect...\n";
    // Adjust this path if your db.php is located differently relative to the migration script
    $dbConfigPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbConfigPath)) {
        die("Error: Database configuration file not found at " . $dbConfigPath . "\n");
    }
    
    // config/db.php already establishes the $pdo connection globally.
    // We just need to include it.
    require_once $dbConfigPath; 

    // After requiring db.php, $pdo should be available.
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        die("Error: PDO connection could not be established after including db.php. Check your db.php configuration.\n");
    }
    echo "Database connection established via db.php.\n";
}

try {
    // Create plans table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `plans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `features` JSON,
            `base_price_monthly` DECIMAL(10, 2) NOT NULL,
            `included_users` INT NOT NULL DEFAULT 1,
            `price_per_additional_user_monthly` DECIMAL(10, 2) NULL, -- Can be NULL if no extra charge
            `base_price_yearly` DECIMAL(10, 2) NULL,
            `price_per_additional_user_yearly` DECIMAL(10, 2) NULL,
            `currency` VARCHAR(3) NOT NULL DEFAULT 'INR',
            `razorpay_plan_id_monthly` VARCHAR(255) UNIQUE NULL,
            `razorpay_plan_id_yearly` VARCHAR(255) UNIQUE NULL,
            `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ");
    echo "Table `plans` created (if not exists) successfully.\n";

    // Create subscriptions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `organization_id` INT NOT NULL,
            `plan_id` INT NOT NULL,
            `razorpay_subscription_id` VARCHAR(255) UNIQUE NOT NULL,
            `status` ENUM('trialing', 'active', 'halted', 'cancelled', 'past_due', 'completed') NOT NULL DEFAULT 'trialing',
            `billing_interval` ENUM('monthly', 'yearly') NOT NULL,
            `billed_users_count` INT NOT NULL DEFAULT 1,
            `trial_starts_at` TIMESTAMP NULL,
            `trial_ends_at` TIMESTAMP NULL,
            `current_period_start` TIMESTAMP NULL,
            `current_period_end` TIMESTAMP NULL,
            `canceled_at` TIMESTAMP NULL,
            `ends_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE RESTRICT
        );
    ");
    echo "Table `subscriptions` created (if not exists) successfully.\n";

    // Add current_plan_id to organizations table
    // Check if column exists before adding
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `organizations` LIKE 'current_plan_id'");
    $stmt->execute();
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        $pdo->exec("
            ALTER TABLE `organizations`
            ADD COLUMN `current_plan_id` INT NULL AFTER `api_key`;
        ");
        echo "Column `current_plan_id` added to `organizations` table successfully.\n";
        
        // Add foreign key constraint if column was just added
        // It's safer to add FK as a separate statement after column is added.
        $pdo->exec("
            ALTER TABLE `organizations`
            ADD CONSTRAINT `fk_organizations_current_plan_id`
            FOREIGN KEY (`current_plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL;
        ");
        echo "Foreign key `fk_organizations_current_plan_id` added to `organizations` table successfully.\n";
    } else {
        echo "Column `current_plan_id` already exists in `organizations` table. Skipping.\n";
    }

    echo "Migration 2026_01_27_add_subscription_tables completed successfully.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
