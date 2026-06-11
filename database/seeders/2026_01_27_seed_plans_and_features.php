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
        // Check if plan already exists by name
        $stmt_check_plan = $pdo->prepare("SELECT id FROM `plans` WHERE name = :name");
        $stmt_check_plan->execute([':name' => $plan_data['name']]);
        $existing_plan = $stmt_check_plan->fetch(PDO::FETCH_ASSOC);

        if ($existing_plan) {
            echo "Plan '{$plan_data['name']}' already exists (ID: {$existing_plan['id']}). Using existing plan.\n";
            $plan_data['id'] = $existing_plan['id'];
        } else {
            $stmt_insert_plan->execute($plan_data);
            $plan_data['id'] = $pdo->lastInsertId();
            echo "Inserted plan: {$plan_data['name']} (ID: {$plan_data['id']})\n";
        }

        // --- Map Features to Plans ---
        // Clear existing mappings for this plan first
        $stmt_clear = $pdo->prepare("DELETE FROM `plan_features` WHERE plan_id = ?");
        $stmt_clear->execute([$plan_data['id']]);
        
        $plan_features_mapping = [];
        if ($plan_data['name'] == 'Basic Plan') {
            // Starter Plan: Basic CRM Usage
            $plan_features_mapping = [
                // Lead management (all enabled)
                'manage_leads', 'create_leads', 'edit_own_leads', 'add_lead_notes', 'update_lead_status', 'view_own_assigned_leads',
                // User/org/reports
                'basic_user_management', 'manage_users', 'organization_profile', 'basic_reports', 'view_reports',
                // Dashboard/support
                'dashboard_basic', 'email_support'
            ];
        } elseif ($plan_data['name'] == 'Professional Plan') {
            // Growth Plan: Team collaboration + reporting + integrations
            $plan_features_mapping = [
                // Lead management (all enabled)
                'manage_leads', 'create_leads', 'edit_all_leads', 'edit_own_leads', 'delete_leads', 'assign_leads', 'reassign_leads', 'add_lead_notes', 'update_lead_status',
                'view_all_assigned_leads', 'view_unassigned_leads', 'export_leads', 'import_leads',
                // User/org/reports
                'basic_user_management', 'manage_users', 'view_user_list', 'reset_user_passwords', 'organization_profile',
                'basic_reports', 'advanced_reports', 'view_reports', 'export_reports',
                // Automations/integrations
                'basic_automations', 'access_automations', 'create_automations',
                'standard_integrations', 'access_integrations',
                // Meetings
                'view_meetings', 'create_meetings', 'edit_own_meetings', 'edit_all_meetings', 'delete_meetings',
                // Dashboard/support
                'dashboard_basic', 'dashboard_advanced', 'chat_support'
            ];
        } elseif ($plan_data['name'] == 'Enterprise Plan') {
            // Enterprise Plan: Full access
            $plan_features_mapping = [
                // Lead management (all enabled)
                'manage_leads', 'create_leads', 'edit_all_leads', 'edit_own_leads', 'delete_leads', 'assign_leads', 'reassign_leads', 'add_lead_notes', 'update_lead_status',
                'view_all_assigned_leads', 'view_unassigned_leads', 'export_leads', 'import_leads',
                // User/org/reports
                'basic_user_management', 'advanced_user_management', 'manage_users', 'manage_roles', 'view_user_list', 'reset_user_passwords',
                'organization_profile', 'manage_organization', 'access_settings',
                'basic_reports', 'advanced_reports', 'create_custom_reports', 'view_reports', 'export_reports',
                // Automations/integrations
                'basic_automations', 'advanced_automations', 'access_automations', 'create_automations', 'create_org_automations', 'edit_all_automations', 'delete_automations',
                'standard_integrations', 'premium_integrations', 'external_webhooks', 'api_access', 'access_integrations',
                // Meetings
                'view_meetings', 'create_meetings', 'edit_own_meetings', 'edit_all_meetings', 'delete_meetings',
                // Audit/security/support
                'audit_log_view', 'audit_log_full', 'view_activity_log', 'enhanced_security', 'unlimited_users',
                'dedicated_account_manager', 'phone_support', 'chat_support', 'email_support',
                // Dashboard
                'dashboard_basic', 'dashboard_advanced'
            ];
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
        echo "Mapped " . count($plan_features_mapping) . " features for plan: {$plan_data['name']}\n";
    }

    $pdo->commit();
    echo "Seeder 2026_01_27_seed_plans_and_features completed successfully.\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Seeder failed: " . $e->getMessage() . "\n");
}
