<?php
/**
 * Get All Features API
 * GET /api/admin/get_all_features.php
 */

require_once '../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    // Check permission - only administrators should see all knobs
    if (!$permissionManager->hasFeature('manage_users') && !$permissionManager->hasFeature('manage_roles')) {
        ApiResponse::error('Permission denied.', 403);
    }

    $stmt = $pdo->query("
        SELECT * FROM feature_knobs 
        ORDER BY category ASC, knob_name ASC
    ");
    $features = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'features' => $features
    ]);

} catch (Exception $e) {
    ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
