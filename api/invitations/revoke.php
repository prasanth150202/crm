<?php
/**
 * Revoke Invitation API
 * POST /api/invitations/revoke.php
 */

require_once __DIR__ . '/../../includes/api_common.php';
require_once __DIR__ . '/../../config/invitation_manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    $pdo = getDb();
    $permissionManager = getPermissionManager($pdo, $user);
    
    // Check permission
    if (!$permissionManager->hasFeature('manage_users')) {
        ApiResponse::error('Permission denied. Only administrators can revoke invitations.', 403);
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['invitation_id'])) {
        ApiResponse::error('Missing required field: invitation_id');
    }
    
    $invitationId = (int)$input['invitation_id'];
    
    // Verify invitation belongs to current organization
    $stmt = $pdo->prepare("
        SELECT org_id, email FROM invitation_tokens 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $invitationId]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        ApiResponse::error('Invitation not found');
    }
    
    if ($invitation['org_id'] != $user['org_id']) {
        ApiResponse::error('Permission denied. Invitation belongs to different organization.', 403);
    }
    
    // Revoke invitation
    $success = InvitationManager::revokeInvitation($pdo, $invitationId, $user['id']);
    
    if (!$success) {
        ApiResponse::error('Failed to revoke invitation. It may already be used or revoked.');
    }
    
    ApiResponse::success(['message' => 'Invitation revoked successfully']);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
