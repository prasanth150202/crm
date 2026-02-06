<?php
// api/users/all_orgs.php
// Get users from all organizations (super admin only)

header("Content-Type: application/json");
require_once '../../config/db.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

try {
    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    
    // Check if super admin
    if (!$user['is_super_admin']) {
        http_response_code(403);
        echo json_encode(["error" => "Permission denied. Super admin access required."]);
        exit;
    }
    
    // Get all users with organization name
    $stmt = $pdo->query("
        SELECT 
            u.*,
            o.name as org_name,
            (SELECT COUNT(*) FROM leads WHERE assigned_to = u.id) as lead_count
        FROM users u
        LEFT JOIN organizations o ON u.org_id = o.id
        ORDER BY o.name, u.full_name
    ");
    
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch users: ' . $e->getMessage()
    ]);
}
