<?php
// database/seeders/2026_01_27_seed_plans_and_features.php

echo "Running seeder: 2026_01_27_seed_plans_and_features...\n";

// Ensure a PDO connection is available or attempt to establish one
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "PDO connection not found. Attempting to load config and connect...\n";
    $dbConfigPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbConfigPath)) {
        die("Error: Database configuration file not found at " . $dbConfigPath . "\n");
    }
    
    require_once $dbConfigPath; 

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        die("Error: PDO connection could not be established after including db.php. Check your db.php configuration.\n");
    }
    echo "Database connection established via db.php.\n";
}

try {
    $pdo->beginTransaction();

    // --- Insert Plans ---
    $plans = [
        [
            'name' => 'Basic Plan',
            'description' => 'Essential tools for small teams and individuals.',
            'base_price_monthly' => 1999.00,
            'included_users' => 3,
            'price_per_additional_user_monthly' => 300.00,
            'base_price_yearly' => 19999.00, // Example yearly price
            'price_per_additional_user_yearly' => 250.00, // Example yearly per-user price
            'currency' => 'INR',
            'features' => json_encode([
                "Up to 3 active users (300 INR/month per additional user)",
                "Standard lead tracking and management",
                "Basic reports",
                "Email support"
            ]),
        ],
        [
            'name' => 'Professional Plan',
            'description' => 'Advanced features for growing teams.',
            'base_price_monthly' => 4999.00,
            'included_users' => 5,
            'price_per_additional_user_monthly' => 500.00,
            'base_price_yearly' => 49999.00, // Example yearly price
            'price_per_additional_user_yearly' => 400.00, // Example yearly per-user price
            'currency' => 'INR',
            'features' => json_encode([
                "Up to 5 active users (500 INR/month per additional user)",
                "All Basic Plan features",
                "Customizable lead fields",
                "Advanced reporting and analytics",
                "Limited lead workflow automation",
                "Standard third-party integrations",
                "Priority email and chat support"
            ]),
        ],
        [
            'name' => 'Enterprise Plan',
            'description' => 'Full power and extensive customization for large organizations (custom pricing).',
            'base_price_monthly' => 0.00, // Custom pricing, will be handled off-platform
            'included_users' => 0, // Custom pricing, user count handled via quote
            'price_per_additional_user_monthly' => 0.00,
            'base_price_yearly' => 0.00,
            'price_per_additional_user_yearly' => 0.00,
            'currency' => 'INR',
            'features' => json_encode([
                "Custom pricing",
                "Unlimited active users (billed per user based on custom quote)",
                "All Professional Plan features",
                "Full automation suite",
                "Premium integrations (Salesforce, HubSpot)",
                "API access",
                "Dedicated account manager",
                "24/7 Phone, email, and chat support"
            ]),
        ],
    ];

    $stmt_insert_plan = $pdo->prepare(
        "INSERT INTO `plans` (name, description, base_price_monthly, included_users, price_per_additional_user_monthly, base_price_yearly, price_per_additional_user_yearly, currency, features)"
        ."VALUES (:name, :description, :base_price_monthly, :included_users, :price_per_additional_user_monthly, :base_price_yearly, :price_per_additional_user_yearly, :currency, :features)"
    );

    foreach ($plans as $plan_data) {
        // Check if plan already exists by name to prevent duplicates on re-run
        $stmt_check_plan = $pdo->prepare("SELECT id FROM `plans` WHERE name = :name");
        $stmt_check_plan->execute([':name' => $plan_data['name']]);
        if ($stmt_check_plan->fetch()) {
            echo "Plan '{$plan_data['name']}' already exists. Skipping insertion.\n";
            continue;
        }

        $stmt_insert_plan->execute($plan_data);
        $plan_data['id'] = $pdo->lastInsertId(); // Store the new ID for feature mapping
        echo "Inserted plan: {$plan_data['name']} (ID: {$plan_data['id']})\n";

        // --- Map Features to Plans ---
        $plan_features_mapping = [];
        if ($plan_data['name'] == 'Basic Plan') {
            $plan_features_mapping = [
                'manage_leads', 'basic_user_management', 'organization_profile', 'basic_reports', 'email_support', 'dashboard_basic', 'manage_users'
            ];
        } elseif ($plan_data['name'] == 'Professional Plan') {
            $plan_features_mapping = array_merge(
                ['manage_leads', 'basic_user_management', 'organization_profile', 'basic_reports', 'email_support', 'dashboard_basic', 'manage_users'], // Basic features
                ['advanced_user_management', 'custom_lead_fields', 'advanced_reports', 'basic_automations', 'standard_integrations', 'chat_support', 'dashboard_advanced', 'audit_log_view']
            );
        } elseif ($plan_data['name'] == 'Enterprise Plan') {
            $plan_features_mapping = array_merge(
                ['manage_leads', 'basic_user_management', 'organization_profile', 'basic_reports', 'email_support', 'dashboard_basic', 'manage_users',
                 'advanced_user_management', 'custom_lead_fields', 'advanced_reports', 'basic_automations', 'standard_integrations', 'chat_support', 'dashboard_advanced', 'audit_log_view'], // Professional features
                ['unlimited_users', 'advanced_automations', 'premium_integrations', 'api_access', 'dedicated_account_manager', 'enhanced_security', 'audit_log_full', 'phone_support']
            );
        }

        $stmt_insert_plan_feature = $pdo->prepare(
            "INSERT IGNORE INTO `plan_features` (plan_id, knob_key)"
            ."VALUES (:plan_id, :knob_key)"
        );

        foreach ($plan_features_mapping as $knob_key) {
            $stmt_insert_plan_feature->execute([
                ':plan_id' => $plan_data['id'],
                ':knob_key' => $knob_key
            ]);
        }
        echo "Mapped features for plan: {$plan_data['name']}\n";
    }

    $pdo->commit();
    echo "Seeder 2026_01_27_seed_plans_and_features completed successfully.\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Seeder failed: " . $e->getMessage() . "\n");
}
