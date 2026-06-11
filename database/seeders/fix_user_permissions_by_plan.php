<?php
/**
 * Fix User Permissions Based on Organization Plan
 * 
 * This script removes all user permissions and re-assigns only the features
 * that are included in the user's organization's current plan.
 */

require_once __DIR__ . '/../../config/db.php';

echo "\nRunning seeder: fix_user_permissions_by_plan...\n";

try {
    $pdo = getDb();
    echo "Database connection established.\n";

    // Start transaction
    $pdo->beginTransaction();

    // Get all users with their organization's plan
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.email,
            u.org_id,
            o.name as org_name,
            o.current_plan_id,
            p.name as plan_name
        FROM users u
        JOIN organizations o ON u.org_id = o.id
        LEFT JOIN plans p ON o.current_plan_id = p.id
        WHERE o.current_plan_id IS NOT NULL
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($users) . " users to process.\n\n";

    foreach ($users as $user) {
        echo "Processing user: {$user['email']} (ID: {$user['user_id']})\n";
        echo "  Organization: {$user['org_name']} (ID: {$user['org_id']})\n";
        echo "  Plan: {$user['plan_name']} (ID: {$user['current_plan_id']})\n";

        // Delete all existing permissions for this user
        $deleteStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $deleteStmt->execute([$user['user_id']]);
        $deletedCount = $deleteStmt->rowCount();
        echo "  Removed {$deletedCount} existing permissions\n";

        // Get features for this plan
        $featuresStmt = $pdo->prepare("
            SELECT knob_key 
            FROM plan_features 
            WHERE plan_id = ?
        ");
        $featuresStmt->execute([$user['current_plan_id']]);
        $features = $featuresStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($features)) {
            echo "  WARNING: No features found for plan {$user['plan_name']}\n";
            continue;
        }

        // Insert plan-specific features
        $insertStmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, knob_key, is_enabled, updated_at) 
            VALUES (?, ?, 1, NOW())
        ");

        $addedCount = 0;
        foreach ($features as $knobKey) {
            $insertStmt->execute([$user['user_id'], $knobKey]);
            $addedCount++;
        }

        echo "  Added {$addedCount} plan-specific features: " . implode(', ', $features) . "\n";
        echo "  ✓ User permissions updated successfully\n\n";
    }

    // Commit transaction
    $pdo->commit();

    echo "\n✅ Seeder fix_user_permissions_by_plan completed successfully.\n";
    echo "Total users processed: " . count($users) . "\n\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
