<?php
/**
 * Get Current User Permissions API
 * GET /api/auth/get_current_permissions.php
 * 
 * Returns the calculated permissions for the authenticated user,
 * taking into account both their role/user permissions AND the organization's plan features.
 */

header("Content-Type: application/json");
require_once '../../includes/api_common.php'; // This provides $pdo and $currentUser
require_once '../../config/permissions.php';

try {
    if (!isset($currentUser) || !$currentUser) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Unauthorized"]);
        exit;
    }

    $pm = getPermissionManager($pdo, $currentUser);
    $permissions = $pm->getCalculatedPermissions();

    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'org_id' => $currentUser['org_id'],
        'role' => $currentUser['role']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch permissions: ' . $e->getMessage()
    ]);
}
