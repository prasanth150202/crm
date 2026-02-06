<?php
// api/reports/get_charts.php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$org_id = $_SESSION['org_id'];
$user_id = $_SESSION['user_id'];
$is_super_admin = $_SESSION['is_super_admin'] ?? false;

error_log("get_charts.php: User ID: $user_id, Org ID: $org_id, Super Admin: $is_super_admin");

try {
    // For super admin, get global charts; for regular users, get by org_id
    if ($is_super_admin) {
        // Super admins see both global and org-specific for current org
        $stmt = $pdo->prepare("SELECT id, title, chart_type as type, data_field as dataField, aggregation, x_axis as xAxis, color_scheme as colorScheme, chart_sort as chartSort, show_total as showTotal, sort_order FROM custom_charts WHERE (org_id = ? OR global = 1) ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$org_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, chart_type as type, data_field as dataField, aggregation, x_axis as xAxis, color_scheme as colorScheme, chart_sort as chartSort, show_total as showTotal, sort_order FROM custom_charts WHERE org_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$org_id]);
    }
    
    $charts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $json = json_encode($charts, JSON_INVALID_UTF8_SUBSTITUTE);
    
    if ($json === false) {
        error_log("get_charts.php JSON Error: " . json_last_error_msg());
        http_response_code(500);
        echo json_encode(["error" => "JSON encoding failed: " . json_last_error_msg()]);
    } else {
        echo $json;
    }
} catch (Exception $e) {
    error_log("get_charts.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>