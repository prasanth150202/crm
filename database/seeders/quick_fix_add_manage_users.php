<?php
/**
 * Quick Fix: Add manage_users permission to all users
 * This is a temporary fix to restore basic functionality
 */

require_once __DIR__ . '/../../config/db.php';

echo "\nAdding manage_users permission to all users...\n";

try {
    $pdo = getDb();
    
    // Get all users
    $stmt = $pdo->prepare("SELECT id, email FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users\n\n";
    
    // Add manage_users permission to each user
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at) 
        VALUES (?, 'manage_users', 1, NOW())
    ");
    
    foreach ($users as $user) {
        $insertStmt->execute([$user['id']]);
        echo "Added manage_users permission to: {$user['email']}\n";
    }
    
    echo "\n✅ Successfully added manage_users permission to all users\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
