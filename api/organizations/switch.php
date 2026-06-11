<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['org_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing org_id']);
    exit;
}

try {
    // Check if user is super admin
    $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // If not super admin, verify access to organization and check status
    if (!$user['is_super_admin']) {
        $stmt = $pdo->prepare("
            SELECT o.id, o.is_active, o.status 
            FROM user_organizations uo
            JOIN organizations o ON uo.org_id = o.id
            WHERE uo.user_id = :user_id AND uo.org_id = :org_id AND uo.is_active = 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id'], ':org_id' => $data['org_id']]);
        $targetOrg = $stmt->fetch();
        
        if (!$targetOrg) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this organization']);
            exit;
        }

        if ($targetOrg['status'] === 'suspended' || (!$targetOrg['is_active'] && $targetOrg['status'] !== 'pending_payment')) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden', 'message' => 'The target organization is suspended or inactive.']);
            exit;
        }
    }
    
    // Update user's current org_id
    $stmt = $pdo->prepare("UPDATE users SET org_id = :org_id WHERE id = :user_id");
    $stmt->execute([':org_id' => $data['org_id'], ':user_id' => $_SESSION['user_id']]);
    
    // 🔥 CRITICAL: Update the session so index.php picks up the new org_id
    $_SESSION['org_id'] = (int)$data['org_id'];
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
