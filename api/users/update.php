<?php
// api/users/update.php
// Update user information

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

// Check permission
$pm = getPermissionManager($pdo, $currentUser);
$pm->requirePermission('manage_users');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required field: id'
    ]);
    exit;
}

try {
    // Verify user exists and belongs to same org
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND org_id = :org_id");
    $stmt->execute([
        ':id' => $data['id'],
        ':org_id' => $currentUser['org_id']
    ]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }
    
    // Cannot modify owner
    if ($user['role'] === 'owner') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot modify owner account'
        ]);
        exit;
    }
    
    // Build update query
    $updates = [];
    $params = [':id' => $data['id']];
    
    if (isset($data['full_name'])) {
        $updates[] = "full_name = :full_name";
        $params[':full_name'] = $data['full_name'];
    }
    
    if (isset($data['email'])) {
        $updates[] = "email = :email";
        $params[':email'] = $data['email'];
    }
    

    if (isset($data['role'])) {
        $updates[] = "role = :role";
        $params[':role'] = $data['role'];
    }

    if (isset($data['is_active'])) {
        $updates[] = "is_active = :is_active";
        $params[':is_active'] = $data['is_active'] ? 1 : 0;
    }

    if (isset($data['is_super_admin'])) {
        $updates[] = "is_super_admin = :is_super_admin";
        $params[':is_super_admin'] = $data['is_super_admin'] ? 1 : 0;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No fields to update'
        ]);
        exit;
    }
    
    // Update user
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Log activity
    $pm->logActivity('user_updated', null, "Updated user: {$user['email']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update user: ' . $e->getMessage()
    ]);
}
