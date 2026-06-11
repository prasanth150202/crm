<?php
/**
 * Seeder to populate feature_knobs table
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "\nPopulating feature_knobs table...\n\n";

$features = [
    // Basic Features
    'manage_leads' => 'Manage Leads',
    'basic_user_management' => 'Basic User Management',
    'organization_profile' => 'Organization Profile',
    'basic_reports' => 'Basic Reports',
    'email_support' => 'Email Support',
    'dashboard_basic' => 'Basic Dashboard',
    'manage_users' => 'Manage Users',

    // Professional Features
    'advanced_user_management' => 'Advanced User Management',
    'custom_lead_fields' => 'Custom Lead Fields',
    'advanced_reports' => 'Advanced Reports',
    'basic_automations' => 'Basic Automations',
    'standard_integrations' => 'Standard Integrations',
    'chat_support' => 'Chat Support',
    'dashboard_advanced' => 'Advanced Dashboard',
    'audit_log_view' => 'View Audit Logs',

    // Enterprise Features
    'unlimited_users' => 'Unlimited Users',
    'advanced_automations' => 'Advanced Automations',
    'external_webhooks' => 'External Webhooks',
    'premium_integrations' => 'Premium Integrations',
    'api_access' => 'API Access',
    'dedicated_account_manager' => 'Dedicated Account Manager',
    'enhanced_security' => 'Enhanced Security',
    'audit_log_full' => 'Full Audit Logs',
    'phone_support' => 'Phone Support'
];

$stmt = $pdo->prepare("INSERT IGNORE INTO feature_knobs (knob_key, description) VALUES (?, ?)");

$count = 0;
foreach ($features as $key => $name) {
    echo "Inserting: $key ($name)\n";
    $stmt->execute([$key, $name]);
    if ($stmt->rowCount() > 0) {
        $count++;
    }
}

echo "\n✅ Inserted $count new features into feature_knobs\n";
