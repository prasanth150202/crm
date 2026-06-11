<?php
/**
 * Save field configurations for an organization
 */
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../config/middleware.php';
require_once '../../config/response.php';

Middleware::apply(['session' => true]);
$user = Middleware::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$org_id = $user['org_id'];

// Check permissions (only admin/owner)
if ($user['role'] !== 'admin' && $user['role'] !== 'owner') {
    ApiResponse::error('Permission denied', 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['field_config']) || !is_array($data['field_config'])) {
    ApiResponse::error('Invalid field configuration', 400);
}

try {
    // Get current settings
    $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch();
    
    $settings = $org && $org['settings'] ? json_decode($org['settings'], true) : [];
    if (!is_array($settings)) {
        $settings = [];
    }
    
    // Update field configuration
    $settings['field_config'] = $data['field_config'];
    
    // Save back to database
    $stmt = $pdo->prepare("UPDATE organizations SET settings = ? WHERE id = ?");
    $stmt->execute([json_encode($settings), $org_id]);
    
    ApiResponse::success(null, 'Field configuration saved successfully');
    
} catch (Exception $e) {
    Logger::exception($e);
    ApiResponse::error('Failed to save field configuration', 500);
}

