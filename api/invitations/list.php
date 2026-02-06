<?php

/**
 * List Invitations API
 * GET /api/invitations/list.php
 */

require_once __DIR__ . '/../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    $pdo = getDb();
    $permissionManager = getPermissionManager($pdo, $user);
    
    // Check permission
    if (!$permissionManager->hasFeature('manage_users')) {
        ApiResponse::error('Permission denied. Only administrators can view invitations.', 403);
    }
    
    // Get invitations for current organization
    $stmt = $pdo->prepare("
        SELECT 
            it.*,
            creator.full_name as created_by_name,
            activator.full_name as used_by_name,
            revoker.full_name as revoked_by_name,
            CASE 
                WHEN it.used_at IS NOT NULL THEN 'used'
                WHEN it.revoked_at IS NOT NULL THEN 'revoked'
                WHEN it.expires_at < NOW() THEN 'expired'
                ELSE 'pending'
            END as status
        FROM invitation_tokens it
        LEFT JOIN users creator ON it.created_by = creator.id
        LEFT JOIN users activator ON it.used_by = activator.id
        LEFT JOIN users revoker ON it.revoked_by = revoker.id
        WHERE it.org_id = :org_id
        ORDER BY it.created_at DESC
    ");
    
    $stmt->execute([':org_id' => $user['org_id']]);
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode features and remove token hash
    foreach ($invitations as &$invitation) {
        $invitation['assigned_features'] = json_decode($invitation['assigned_features'], true);
        unset($invitation['token_hash']); // Don't expose token hash
    }
    
    ApiResponse::success(['invitations' => $invitations]);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
