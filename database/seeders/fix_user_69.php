<?php
/**
 * Add manage_users permission to specific user
 */

require_once __DIR__ . '/../../config/db.php';

$userId = 69; // The user ID from the error

echo "\nAdding manage_users permission to user ID: {$userId}...\n";

try {
    $pdo = getDb();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User ID {$userId} not found\n";
        exit(1);
    }
    
    echo "Found user: {$user['email']}\n";
    
    // Add manage_users permission
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at) 
        VALUES (?, 'manage_users', 1, NOW())
    ");
    $insertStmt->execute([$userId]);
    
    echo "✅ Added manage_users permission to user {$user['email']}\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
