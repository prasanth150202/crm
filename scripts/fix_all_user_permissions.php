<?php
// scripts/fix_all_user_permissions.php
// This script will ensure all users have the correct permissions based on their organization's current plan.

require_once __DIR__ . '/../config/db.php';
$pdo = getDb();

// Get all users with their org and org's current plan
$sql = "
    SELECT u.id AS user_id, u.org_id, o.current_plan_id
    FROM users u
    JOIN organizations o ON u.org_id = o.id
    WHERE o.current_plan_id IS NOT NULL
";
$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$insertStmt = $pdo->prepare(
    "INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at) VALUES (?, ?, 1, NOW())"
);

$count = 0;
foreach ($users as $user) {
    $planId = $user['current_plan_id'];
    $userId = $user['user_id'];
    // Get all features for this plan
    $features = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
    $features->execute([$planId]);
    $knobs = $features->fetchAll(PDO::FETCH_COLUMN);
    foreach ($knobs as $knob) {
        $insertStmt->execute([$userId, $knob]);
        $count++;
    }
}
echo "Updated permissions for all users. Total permissions inserted: $count\n";
