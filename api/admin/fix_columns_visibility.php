<?php
/**
 * Fix for hidden Manage Columns button
 * This script ensures the manage_custom_fields knob exists and is assigned to all plans and existing admin roles.
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "Starting fix for Manage Columns visibility...\n\n";

try {
    // 1. Ensure the knob exists in feature_knobs
    $stmt = $pdo->prepare("INSERT IGNORE INTO feature_knobs (knob_key, knob_name, description, category, is_system) 
                          VALUES ('manage_custom_fields', 'Manage Custom Fields', 'Can create and modify custom fields', 'settings', 1)");
    $stmt->execute();
    echo "Checked manage_custom_fields in feature_knobs.\n";

    // 2. Assign to all plans in plan_features
    $plans = [1, 2, 3];
    foreach ($plans as $planId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO plan_features (plan_id, knob_key) VALUES (?, 'manage_custom_fields')");
        $stmt->execute([$planId]);
    }
    echo "Assigned manage_custom_fields to all plans in plan_features.\n";

    // 3. Fix existing organization admin roles
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id)
        SELECT 'admin', 'manage_custom_fields', 1, id FROM organizations
    ");
    $stmt->execute();
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id)
        SELECT 'owner', 'manage_custom_fields', 1, id FROM organizations
    ");
    $stmt->execute();
    echo "Fixed roles for existing organizations.\n";

    // 4. Fix existing user permissions (for the UI gates that use user_permissions)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled)
        SELECT id, 'manage_custom_fields', 1 FROM users WHERE role IN ('admin', 'owner')
    ");
    $stmt->execute();
    echo "Fixed user permissions for existing admins/owners.\n";

    echo "\n✅ Successfully fixed Manage Columns visibility!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
