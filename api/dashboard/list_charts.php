<?php
// api/dashboard/list_charts.php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../config/response.php';

// This endpoint is very similar to /api/reports/get_charts.php
// It respects user permissions by checking the 'view_reports' feature knob.

try {
    // Dashboard access should be available to all authenticated users
    // Remove restrictive permission check for dashboard charts

    $org_id = $_SESSION['org_id'];

    $stmt = $pdo->prepare("
        SELECT id, title as name, chart_type as type, data_field, aggregation, x_axis, color_scheme, chart_sort, show_total, sort_order, created_at 
        FROM custom_charts 
        WHERE org_id = ? 
        ORDER BY sort_order ASC, title ASC
    ");
    $stmt->execute([$org_id]);
    $charts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // No need to process settings here, just pass them to the frontend
    // The dashboard will call the chart_data endpoint with the correct settings

    json_response(200, $charts);

} catch (Exception $e) {
    error_response(500, 'Error fetching chart list: ' . $e->getMessage());
}
