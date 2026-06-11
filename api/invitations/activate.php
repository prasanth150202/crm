<?php
/**
 * Activate Invitation API
 * POST /api/invitations/activate.php
 */

require_once '../../config/db.php';
require_once '../../config/invitation_manager.php';
require_once '../../config/response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    $pdo = getDb();
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['token', 'password', 'full_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            ApiResponse::error("Missing required field: $field");
        }
    }
    
    $token = $input['token'];
    $password = $input['password'];
    $fullName = trim($input['full_name']);
    
    // Activate invitation
    $result = InvitationManager::activateInvitation($pdo, $token, $password, $fullName);
    
    if (!$result['success']) {
        if (isset($result['password_errors'])) {
            ApiResponse::error($result['error'], 400, [
                'password_errors' => $result['password_errors']
            ]);
        }
        ApiResponse::error($result['error']);
    }
    
    ApiResponse::success([
        'user_id' => $result['user_id'],
        'message' => 'Account activated successfully. You can now log in.'
    ]);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
