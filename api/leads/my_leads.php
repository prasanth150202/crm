<?php
// api/leads/my_leads.php
// Get leads assigned to current user

header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../config/permissions.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(["error" => "User not found"]);
    exit;
}

try {
    // Get leads assigned to current user
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            u.full_name as assigned_to_name,
            u.email as assigned_to_email
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        WHERE l.org_id = :org_id 
        AND l.assigned_to = :user_id
        ORDER BY l.created_at DESC
    ");
    
    $stmt->execute([
        ':org_id' => $currentUser['org_id'],
        ':user_id' => $currentUser['id']
    ]);
    
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
