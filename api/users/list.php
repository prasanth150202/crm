<?php
// api/users/list.php
// List all users in organization

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

try {
    // Get all users in organization (include is_super_admin)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.email,
            u.full_name,
            u.role,
            u.avatar_url,
            u.is_active,
            u.created_at,
            u.last_login,
            u.is_super_admin,
            COUNT(DISTINCT l.id) as lead_count,
            COUNT(DISTINCT t.team_id) as team_count
        FROM users u
        LEFT JOIN leads l ON l.assigned_to = u.id AND l.org_id = u.org_id
        LEFT JOIN team_members t ON t.user_id = u.id
        WHERE u.org_id = :org_id
        GROUP BY u.id
        ORDER BY 
            FIELD(u.role, 'owner', 'admin', 'manager', 'staff'),
            u.created_at DESC
    ");
    $stmt->execute([':org_id' => $currentUser['org_id']]);
    $users = $stmt->fetchAll();
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch users: ' . $e->getMessage()
    ]);
}
