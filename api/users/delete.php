<?php
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../config/permissions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(["error" => "User not found"]);
    exit;
}

// Only admin and owner can delete users
if (!in_array($currentUser['role'], ['admin', 'owner'])) {
    http_response_code(403);
    echo json_encode(["error" => "Only admins and owners can delete users"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}

try {
    // Get user to delete
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND org_id = :org_id");
    $stmt->execute([':id' => $data['user_id'], ':org_id' => $currentUser['org_id']]);
    $userToDelete = $stmt->fetch();
    
    if (!$userToDelete) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Prevent deleting yourself
    if ($userToDelete['id'] == $currentUser['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
        exit;
    }
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND org_id = :org_id");
    $stmt->execute([':id' => $data['user_id'], ':org_id' => $currentUser['org_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete user: ' . $e->getMessage()
    ]);
}
