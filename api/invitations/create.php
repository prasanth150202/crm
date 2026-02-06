<?php
/**
 * Create Invitation API
 * POST /api/invitations/create.php
 */

// DEBUG: Show all errors for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/api_common.php';
require_once __DIR__ . '/../../config/invitation_manager.php';

header('Content-Type: application/json');



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    $pdo = getDb();
    $permissionManager = getPermissionManager($pdo, $user);
    
    // Check permission - only users with manage_users can create invitations
    if (!$permissionManager->hasFeature('manage_users')) {
        ApiResponse::error('Permission denied. Only administrators can create invitations.', 403);
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['email'])) {
        ApiResponse::error('Missing required field: email');
    }
    
    if (empty($input['role'])) {
        ApiResponse::error('Missing required field: role');
    }
    
    if (empty($input['features']) || !is_array($input['features'])) {
        ApiResponse::error('Missing or invalid field: features (must be an array)');
    }
    
    $email = trim($input['email']);
    $role = $input['role'];
    $features = $input['features'];
    $expiryDays = isset($input['expiry_days']) ? (int)$input['expiry_days'] : 7;
    
    // Validate role
    $validRoles = ['owner', 'admin', 'manager', 'staff'];
    if (!in_array($role, $validRoles)) {
        ApiResponse::error('Invalid role. Must be one of: ' . implode(', ', $validRoles));
    }
    
    // Validate features exist
    $stmt = $pdo->prepare("SELECT knob_key FROM feature_knobs WHERE knob_key IN (" . 
        implode(',', array_fill(0, count($features), '?')) . ")");
    $stmt->execute($features);
    $validFeatures = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $invalidFeatures = array_diff($features, $validFeatures);
    if (!empty($invalidFeatures)) {
        ApiResponse::error('Invalid features: ' . implode(', ', $invalidFeatures));
    }
    
    // Create invitation
    $result = InvitationManager::createInvitation(
        $pdo,
        $user['org_id'],
        $email,
        $role,
        $features,
        $user['id'],
        $expiryDays
    );
    
    if (!$result['success']) {
        ApiResponse::error($result['error']);
    }
    
    // Log activity
    $permissionManager->logActivity(
        'invitation_created',
        null,
        "Created invitation for: $email with role: $role"
    );
    
    // Build invitation URL only if token is present
    if (empty($result['token'])) {
        ApiResponse::error('Invitation created but token is missing. Please check the backend logic.', 500);
    }
    // Use APP_URL from env for base URL
    require_once __DIR__ . '/../../config/env.php';
    $baseUrl = Env::get('APP_URL', 'http://localhost/leads2');
    $invitationUrl = rtrim($baseUrl, '/') . '/activate.php?token=' . $result['token'];

    // Return invitation_url and other fields at the top level for frontend compatibility
    echo json_encode([
        'success' => true,
        'invitation_id' => $result['invitation_id'],
        'invitation_url' => $invitationUrl,
        'token' => $result['token'],
        'expires_at' => $result['expires_at'],
        'message' => 'Invitation created successfully'
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (PDOException $e) {
    error_log('[INVITATION CREATE] SQL Error: ' . $e->getMessage());
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('[INVITATION CREATE] General Error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
