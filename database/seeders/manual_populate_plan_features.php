<?php
/**
 * Manually populate plan_features table
 * Updated to be comprehensive and match production requirements
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "\nManually populating plan_features table...\n\n";

// Clear existing data
$pdo->exec("DELETE FROM plan_features");
echo "Cleared existing plan_features\n\n";

// Define helper for common features
$basicFeatures = [
    'manage_leads', 'basic_user_management', 'organization_profile', 'basic_reports', 
    'email_support', 'dashboard_basic', 'manage_users', 'create_leads', 
    'view_all_assigned_leads', 'view_own_assigned_leads', 'view_unassigned_leads', 
    'update_lead_status', 'add_lead_notes', 'assign_leads', 'reassign_leads', 
    'view_reports', 'view_meetings', 'create_meetings', 'edit_own_meetings',
    'manage_custom_fields' // Essential for Column visibility management
];

$proFeatures = array_merge($basicFeatures, [
    'advanced_user_management', 'custom_lead_fields', 'advanced_reports', 
    'basic_automations', 'standard_integrations', 'chat_support', 
    'dashboard_advanced', 'audit_log_view', 'import_leads', 'export_leads',
    'access_automations', 'access_integrations', 'edit_all_meetings', 'delete_meetings',
    'reset_user_passwords', 'view_user_list'
]);

$enterpriseFeatures = array_merge($proFeatures, [
    'unlimited_users', 'advanced_automations', 'external_webhooks', 
    'premium_integrations', 'api_access', 'dedicated_account_manager', 
    'enhanced_security', 'audit_log_full', 'phone_support',
    'access_settings', 'manage_organization', 'manage_roles', 
    'view_activity_log', 'create_custom_reports', 'create_org_automations',
    'edit_all_automations', 'delete_automations', 'manage_zingbot_settings'
]);

// Define features for each plan
$planFeatures = [
    1 => $basicFeatures,
    2 => $proFeatures,
    3 => $enterpriseFeatures
];

// Get valid features from feature_knobs
$stmt = $pdo->prepare("SELECT knob_key FROM feature_knobs");
$stmt->execute();
$validFeaturesArr = $stmt->fetchAll(PDO::FETCH_COLUMN);
$validFeatures = array_flip($validFeaturesArr); // For faster lookup

$stmt = $pdo->prepare("INSERT IGNORE INTO plan_features (plan_id, knob_key) VALUES (?, ?)");

foreach ($planFeatures as $planId => $features) {
    echo "Processing Plan ID $planId:\n";
    $count = 0;
    foreach ($features as $feature) {
        if (!isset($validFeatures[$feature])) {
            // Check if it's an alias or if it exists in DB regardless of seeder
            echo "  ⚠️  Skipping unknown feature: $feature\n";
            continue; 
        }
        $stmt->execute([$planId, $feature]);
        $count++;
    }
    echo "  Inserted $count features\n\n";
}

echo "✅ Done!\n";
