<?php
// api/organizations/list.php
// List all organizations (super admin only)

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
    // Get current user's ID
    $user_id = $_SESSION['user_id'];
    
    // Get organizations the current user is a member of, along with stats
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.name,
            o.is_active,
            o.created_at,
            (SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.org_id = o.id) as user_count,
            (SELECT COUNT(DISTINCT l.id) FROM leads l WHERE l.org_id = o.id) as lead_count,
            (SELECT email FROM users WHERE org_id = o.id AND role = 'admin' LIMIT 1) as owner_email
        FROM organizations o
        JOIN user_organizations uo ON o.id = uo.org_id
        WHERE uo.user_id = :user_id AND uo.is_active = 1
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    
    $organizations = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'organizations' => $organizations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch organizations: ' . $e->getMessage()
    ]);
}
