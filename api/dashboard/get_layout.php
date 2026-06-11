<?php
// api/dashboard/get_layout.php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../config/response.php';

try {
    $user_id = $_SESSION['user_id'];
    $org_id = $_SESSION['org_id'];

    $stmt = $pdo->prepare("SELECT layout FROM dashboard_layouts WHERE user_id = ? AND org_id = ?");
    $stmt->execute([$user_id, $org_id]);
    $layout = $stmt->fetchColumn();

    if ($layout) {
        // The layout is stored as a JSON string, so we just send it.
        header('Content-Type: application/json');
        echo $layout;
    } else {
        // No layout found, return a default empty state
        json_response(200, [
            'selectedCharts' => [],
            'dateRange' => 'last_7_days',
            'layout' => [] // For future drag-and-drop layout
        ]);
    }

} catch (Exception $e) {
    error_response(500, 'Error fetching dashboard layout: ' . $e->getMessage());
}
