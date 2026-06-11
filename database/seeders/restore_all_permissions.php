<?php
/**
 * Restore All Permissions to All Users
 * This grants all features from feature_knobs to all users
 */

require_once __DIR__ . '/../../config/db.php';

echo "\nRestoring all permissions to all users...\n";

try {
    $pdo = getDb();
    
    // Get all users
    $stmt = $pdo->prepare("SELECT id, email FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users\n";
    
    // Get all feature knobs
    $stmt = $pdo->prepare("SELECT knob_key FROM feature_knobs");
    $stmt->execute();
    $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($features) . " features\n\n";
    
    if (empty($features)) {
        echo "⚠️  No features found in feature_knobs table!\n";
        exit(1);
    }
    
    // Add all features to each user
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at) 
        VALUES (?, ?, 1, NOW())
    ");
    
    foreach ($users as $user) {
        echo "Processing user: {$user['email']}\n";
        $count = 0;
        
        foreach ($features as $feature) {
            $insertStmt->execute([$user['id'], $feature]);
            $count++;
        }
        
        echo "  Added {$count} permissions\n";
    }
    
    echo "\n✅ Successfully restored all permissions to all users\n";
    echo "Total features per user: " . count($features) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
