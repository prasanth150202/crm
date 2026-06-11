<?php
/**
 * Fix permissions for users based on their organization's plan
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "\nFixing user permissions based on organization plans...\n\n";

// Get all users with their organization's plan
$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.org_id, o.current_plan_id, p.name as plan_name
    FROM users u
    JOIN organizations o ON u.org_id = o.id
    LEFT JOIN plans p ON o.current_plan_id = p.id
    WHERE o.current_plan_id IS NOT NULL
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    echo "Processing: {$user['email']} - Plan: {$user['plan_name']}\n";
    
    // Get features for this plan
    $featStmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
    $featStmt->execute([$user['current_plan_id']]);
    $features = $featStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($features)) {
        echo "  ⚠️  No features found for plan ID {$user['current_plan_id']}\n";
        continue;
    }
    
    // Delete existing permissions
    $delStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $delStmt->execute([$user['id']]);
    
    // Insert plan features
    $insStmt = $pdo->prepare("INSERT INTO user_permissions (user_id, knob_key, is_enabled, updated_at) VALUES (?, ?, 1, NOW())");
    
    foreach ($features as $feature) {
        $insStmt->execute([$user['id'], $feature]);
    }
    
    echo "  ✅ Assigned " . count($features) . " features\n";
}

echo "\n✅ All users updated!\n";
