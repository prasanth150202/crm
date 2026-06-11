<?php
// api/users/change_password.php
// Allow users to change their own password

header("Content-Type: application/json");
require_once '../../config/db.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['current_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: current_password, new_password'
    ]);
    exit;
}

// Validate new password strength
if (strlen($data['new_password']) < 6) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'New password must be at least 6 characters long'
    ]);
    exit;
}

try {
    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }
    
    // Verify current password
    if (!password_verify($data['current_password'], $user['password_hash'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Current password is incorrect'
        ]);
        exit;
    }
    
    // Hash new password
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
    $stmt->execute([
        ':password_hash' => $newPasswordHash,
        ':id' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to change password: ' . $e->getMessage()
    ]);
}
