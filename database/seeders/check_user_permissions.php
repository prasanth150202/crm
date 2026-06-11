<?php
/**
 * Check user permissions for a specific user
 */

require_once __DIR__ . '/../../config/db.php';

// Get the most recent user (likely the one you just logged in with)
$pdo = getDb();

echo "\nChecking recent users and their permissions...\n\n";

// Get recent users
$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.org_id, o.name as org_name, o.current_plan_id, p.name as plan_name
    FROM users u
    JOIN organizations o ON u.org_id = o.id
    LEFT JOIN plans p ON o.current_plan_id = p.id
    ORDER BY u.id DESC
    LIMIT 5
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    echo "User: {$user['email']} (ID: {$user['id']})\n";
    echo "  Organization: {$user['org_name']} (ID: {$user['org_id']})\n";
    echo "  Plan: {$user['plan_name']} (ID: {$user['current_plan_id']})\n";
    
    // Get user permissions
    $permStmt = $pdo->prepare("SELECT knob_key FROM user_permissions WHERE user_id = ? AND is_enabled = 1");
    $permStmt->execute([$user['id']]);
    $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "  Permissions (" . count($permissions) . "): " . implode(', ', $permissions) . "\n";
    
    // Check for automation permissions
    $hasBasic = in_array('basic_automations', $permissions);
    $hasAdvanced = in_array('advanced_automations', $permissions);
    echo "  Has basic_automations: " . ($hasBasic ? 'YES' : 'NO') . "\n";
    echo "  Has advanced_automations: " . ($hasAdvanced ? 'YES' : 'NO') . "\n";
    echo "\n";
}
