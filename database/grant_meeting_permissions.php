<?php
/**
 * Grant Meeting Permissions to Existing Users
 * Run this once to give meeting permissions to existing admin/owner users
 */

// Database configuration
$host = 'localhost';
$db = 'crm';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

header('Content-Type: application/json');

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get all meeting feature knobs
    $stmt = $pdo->query("SELECT knob_key FROM feature_knobs WHERE knob_key LIKE 'view_meetings' OR knob_key LIKE '%_meetings'");
    $meetingFeatures = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all admin and owner users
    $stmt = $pdo->query("SELECT id, email, role FROM users WHERE role IN ('owner', 'admin', 'super_admin')");
    $users = $stmt->fetchAll();
    
    $results = [];
    
    foreach ($users as $userRow) {
        $userId = $userRow['id'];
        $userEmail = $userRow['email'];
        $userRole = $userRow['role'];
        
        $grantedFeatures = [];
        
        foreach ($meetingFeatures as $feature) {
            // Check if permission already exists
            $stmt = $pdo->prepare("SELECT id FROM user_permissions WHERE user_id = ? AND knob_key = ?");
            $stmt->execute([$userId, $feature]);
            
            if (!$stmt->fetch()) {
                // Grant permission
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, knob_key, is_enabled, granted_by)
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$userId, $feature, $userId]);
                $grantedFeatures[] = $feature;
            }
        }
        
        $results[] = [
            'user_id' => $userId,
            'email' => $userEmail,
            'role' => $userRole,
            'granted_features' => $grantedFeatures,
            'count' => count($grantedFeatures)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Meeting permissions granted to admin/owner users',
        'users_updated' => count($users),
        'details' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
