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
    
    // If not super admin, verify access to organization
    if (!$user['is_super_admin']) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_organizations 
            WHERE user_id = :user_id AND org_id = :org_id AND is_active = 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id'], ':org_id' => $data['org_id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this organization']);
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
