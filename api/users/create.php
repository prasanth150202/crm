<?php
// api/users/create.php
// Create new user in organization

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
if (!isset($data['email']) || !isset($data['full_name']) || !isset($data['role'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: email, full_name, role'
    ]);
    exit;
}

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid email address'
    ]);
    exit;
}

// Validate role
$validRoles = ['admin', 'manager', 'staff'];
if (!in_array($data['role'], $validRoles)) {
    // No strict role validation, allow any string
    // (You may remove this block entirely if you want to allow any role)
}

try {
    // Check if user already has access to this organization
    $stmt = $pdo->prepare("
        SELECT uo.id FROM user_organizations uo
        JOIN users u ON u.id = uo.user_id
        WHERE u.email = :email AND uo.org_id = :org_id
    ");
    $stmt->execute([':email' => $data['email'], ':org_id' => $currentUser['org_id']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'User already has access to your organization'
        ]);
        exit;
    }
    
    // Check if user exists globally
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $data['email']]);
    $existingUser = $stmt->fetch();
    
    $tempPassword = null;
    $userId = null;
    
    if ($existingUser) {
        // User exists - just add them to this organization
        $userId = $existingUser['id'];
        // Optionally update is_super_admin and role if provided
        $fieldsToUpdate = [];
        $params = [':id' => $userId];
        if (isset($data['is_super_admin'])) {
            $fieldsToUpdate[] = 'is_super_admin = :is_super_admin';
            $params[':is_super_admin'] = $data['is_super_admin'] ? 1 : 0;
        }
        if (isset($data['role'])) {
            $fieldsToUpdate[] = 'role = :role';
            $params[':role'] = $data['role'];
        }
        if ($fieldsToUpdate) {
            $sql = 'UPDATE users SET ' . implode(', ', $fieldsToUpdate) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    } else {
        // New user - create user record first
        $tempPassword = bin2hex(random_bytes(8));
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, full_name, org_id, is_super_admin, role)
            VALUES (:email, :password_hash, :full_name, :org_id, :is_super_admin, :role)
        ");
        $stmt->execute([
            ':email' => $data['email'],
            ':password_hash' => $passwordHash,
            ':full_name' => $data['full_name'],
            ':org_id' => $currentUser['org_id'],
            ':is_super_admin' => isset($data['is_super_admin']) && $data['is_super_admin'] ? 1 : 0,
            ':role' => isset($data['role']) ? $data['role'] : null
        ]);
        $userId = $pdo->lastInsertId();
    }
    
    // Add user to organization with specified role
    $stmt = $pdo->prepare("
        INSERT INTO user_organizations (user_id, org_id, role, is_active)
        VALUES (:user_id, :org_id, :role, :is_active)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':org_id' => $currentUser['org_id'],
        ':role' => $data['role'],
        ':is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true
    ]);
    
    $newUserId = $userId;
    
    // Log activity
    $pm->logActivity('user_created', null, "Created new user: {$data['full_name']} ({$data['email']}) with role: {$data['role']}");
    
    // TODO: Send email with temporary password
    
    $response = [
        'success' => true,
        'user_id' => $newUserId
    ];
    
    if ($tempPassword) {
        $response['temp_password'] = $tempPassword;
        $response['message'] = 'User created successfully. Temporary password generated.';
    } else {
        $response['message'] = 'User added to organization successfully. They can use their existing password.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create user: ' . $e->getMessage()
    ]);
}
