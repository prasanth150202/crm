<?php
// database/migrations/2026_01_27_add_plan_features_table.php

echo "Running migration: 2026_01_27_add_plan_features_table...\n";

// Ensure a PDO connection is available or attempt to establish one
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "PDO connection not found. Attempting to load config and connect...\n";
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
    // Create plan_features table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `plan_features` (
            `plan_id` INT NOT NULL,
            `knob_key` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`plan_id`, `knob_key`),
            FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`knob_key`) REFERENCES `feature_knobs`(`knob_key`) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table `plan_features` created (if not exists) successfully.\n";

    echo "Migration 2026_01_27_add_plan_features_table completed successfully.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

