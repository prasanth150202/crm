<?php
/**
 * Validate Invitation Token API
 * POST /api/invitations/validate.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/invitation_manager.php';
require_once __DIR__ . '/../../config/response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    $pdo = getDb();
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['token'])) {
        ApiResponse::error('Missing required field: token');
    }
    
    $token = $input['token'];
    
    // Validate token
    $invitation = InvitationManager::validateToken($pdo, $token);
    
    if (!$invitation) {
        ApiResponse::error('Invalid invitation token', 404);
    }
    
    if (isset($invitation['error'])) {
        ApiResponse::error($invitation['error'], 400);
    }
    
    // Return invitation details (without sensitive data)
    ApiResponse::success([
        'email' => $invitation['email'],
        'role' => $invitation['role'],
        'org_name' => $invitation['org_name'],
        'expires_at' => $invitation['expires_at'],
        'features_count' => count($invitation['assigned_features'])
    ]);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
