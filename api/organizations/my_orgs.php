<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

try {
    // Get current user's active org_id for context
    $stmt = $pdo->prepare("SELECT org_id FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Fetch only the organizations the user is explicitly a member of
    $stmt = $pdo->prepare("
        SELECT o.id, o.name, uo.role
        FROM user_organizations uo
        JOIN organizations o ON o.id = uo.org_id
        WHERE uo.user_id = :user_id AND uo.is_active = 1
        ORDER BY o.name
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $orgs = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'organizations' => $orgs,
        'current_org_id' => $user['org_id']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
