<?php
// api/leads/all_orgs.php
// Get leads from all organizations (super admin only)

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
    
    // Get all leads with organization name
    $stmt = $pdo->query("
        SELECT 
            l.*,
            o.name as org_name,
            u.email as assigned_to_email
        FROM leads l
        LEFT JOIN organizations o ON l.org_id = o.id
        LEFT JOIN users u ON l.assigned_to = u.id
        ORDER BY l.created_at DESC
    ");
    
    $leads = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'leads' => $leads,
        'total' => count($leads)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch leads: ' . $e->getMessage()
    ]);
}
