<?php
// api/users/get_permissions.php
// Get permissions for a specific user (Admin only)

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

// Check permission - only admin can view user permissions
$pm = getPermissionManager($pdo, $currentUser);
$pm->requirePermission('manage_users');

// Get user_id from query parameter
$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing user_id parameter'
    ]);
    exit;
}

try {
    // Get all feature knobs
    $knobs = $pdo->query("SELECT knob_key, knob_name, category FROM feature_knobs ORDER BY category, knob_name")->fetchAll();

    // Get user's org and role
    $stmt = $pdo->prepare("SELECT org_id, role FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $userRow = $stmt->fetch();
    $orgId = $userRow['org_id'] ?? null;
    $role = $userRow['role'] ?? null;

    // Get user-specific permissions (overrides)
    $stmt = $pdo->prepare("
        SELECT knob_key, is_enabled
        FROM user_permissions
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $userPerms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get role-based permissions (defaults)
    $rolePerms = [];
    if ($orgId && $role) {
        $stmt = $pdo->prepare("
            SELECT knob_key, is_enabled
            FROM role_permissions
            WHERE org_id = :org_id AND role = :role
        ");
        $stmt->execute([':org_id' => $orgId, ':role' => $role]);
        $rolePerms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Build response: user_permissions override role_permissions
    $permissions = [];
    foreach ($knobs as $knob) {
        $key = $knob['knob_key'];
        if (isset($userPerms[$key])) {
            $permissions[$key] = (bool)$userPerms[$key];
        } elseif (isset($rolePerms[$key])) {
            $permissions[$key] = (bool)$rolePerms[$key];
        } else {
            $permissions[$key] = false;
        }
    }

    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'knobs' => $knobs
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch permissions: ' . $e->getMessage()
    ]);
}
