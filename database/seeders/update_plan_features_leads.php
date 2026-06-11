<?php
/**
 * Update plan_features for lead management
 * 
 * Basic Plan (1): All lead management features
 * Professional Plan (2): All Basic + export_leads + import_leads
 * Enterprise Plan (3): All Professional features (already has most, adds missing)
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "\n=== Updating plan_features for lead management ===\n\n";

// Lead management features for BASIC plan (Plan 1)
$basicLeadFeatures = [
    'manage_leads',
    'create_leads',
    'edit_own_leads',
    'edit_all_leads',
    'delete_leads',
    'assign_leads',
    'reassign_leads',
    'add_lead_notes',
    'update_lead_status',
    'view_own_assigned_leads',  // already exists
    'view_all_assigned_leads',
    'view_unassigned_leads',
];

// Professional plan (Plan 2): All Basic + export/import
$professionalLeadFeatures = array_merge($basicLeadFeatures, [
    'export_leads',
    'import_leads',
]);

// Enterprise plan (Plan 3): Same as Professional (already has some)
$enterpriseLeadFeatures = $professionalLeadFeatures;

$plans = [
    1 => ['name' => 'Basic', 'features' => $basicLeadFeatures],
    2 => ['name' => 'Professional', 'features' => $professionalLeadFeatures],
    3 => ['name' => 'Enterprise', 'features' => $enterpriseLeadFeatures],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO plan_features (plan_id, knob_key) VALUES (?, ?)");

foreach ($plans as $planId => $plan) {
    echo "Plan {$planId} ({$plan['name']}):\n";
    $added = 0;
    foreach ($plan['features'] as $feature) {
        try {
            $stmt->execute([$planId, $feature]);
            if ($stmt->rowCount() > 0) {
                echo "  + Added: {$feature}\n";
                $added++;
            } else {
                echo "  = Already exists: {$feature}\n";
            }
        } catch (PDOException $e) {
            echo "  ✗ Failed: {$feature} - {$e->getMessage()}\n";
        }
    }
    echo "  → {$added} new features added\n\n";
}

// Now sync to user_permissions for existing users
echo "=== Syncing to user_permissions for existing users ===\n\n";

$userStmt = $pdo->prepare("
    SELECT u.id as user_id, u.email, o.current_plan_id
    FROM users u
    JOIN organizations o ON u.org_id = o.id
    WHERE o.current_plan_id IS NOT NULL
");
$userStmt->execute();
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$insertPermStmt = $pdo->prepare("
    INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at)
    VALUES (?, ?, 1, NOW())
");

foreach ($users as $user) {
    $planId = $user['current_plan_id'];
    if (!isset($plans[$planId])) continue;

    $added = 0;
    foreach ($plans[$planId]['features'] as $feature) {
        try {
            $insertPermStmt->execute([$user['user_id'], $feature]);
            if ($insertPermStmt->rowCount() > 0) $added++;
        } catch (PDOException $e) {
            // skip
        }
    }
    echo "User {$user['email']} (Plan {$planId}): +{$added} permissions\n";
}

echo "\n✅ Done! Plan features and user permissions updated.\n";
