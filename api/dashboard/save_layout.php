<?php
// api/dashboard/save_layout.php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['message' => 'Method Not Allowed']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $org_id = $_SESSION['org_id'];
    
    // Get the raw POST data
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    // Basic validation
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['selectedCharts']) || !isset($data['dateRange'])) {
        json_response(400, ['message' => 'Invalid JSON data.']);
        exit;
    }

    // Upsert the layout
    $stmt = $pdo->prepare("
        INSERT INTO dashboard_layouts (user_id, org_id, layout)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE layout = VALUES(layout)
    ");
    
    $stmt->execute([$user_id, $org_id, $json_data]);

    json_response(200, ['message' => 'Dashboard layout saved successfully.']);

} catch (Exception $e) {
    error_response(500, 'Error saving dashboard layout: ' . $e->getMessage());
}
