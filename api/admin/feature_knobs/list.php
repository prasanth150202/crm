<?php
// api/admin/feature_knobs/list.php
// Get all feature knobs with current role permissions (Admin only)

header("Content-Type: application/json");
require_once '../../../config/db.php';
require_once '../../../config/permissions.php';
require_once '../../../config/feature_knobs.php';

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

try {
    $fkm = getFeatureKnobManager($pdo, $currentUser);
    $org_id = $_GET['org_id'] ?? $currentUser['org_id'];
    
    $knobs = $fkm->getAllKnobs($org_id);
    
    // Group by category
    $grouped = [];
    foreach ($knobs as $knob) {
        $category = $knob['category'];
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $knob;
    }
    
    echo json_encode([
        'success' => true,
        'knobs' => $knobs,
        'grouped' => $grouped,
        'total' => count($knobs)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch feature knobs: ' . $e->getMessage()
    ]);
}
