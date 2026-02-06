<?php
// api/reports/save_chart.php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['org_id'];
$is_super_admin = $_SESSION['is_super_admin'] ?? false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$x_axis = $data['x_axis'] ?? 'stage_id';
if (!$data || !isset($data['title']) || !isset($data['chart_type']) || !isset($data['data_field'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: title, chart_type, data_field"]);
    exit;
}

try {
    if ($id) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE custom_charts SET title = ?, chart_type = ?, data_field = ?, aggregation = ?, x_axis = ?, color_scheme = ?, chart_sort = ?, show_total = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?");
        $stmt->execute([
            $data['title'],
            $data['chart_type'],
            $data['data_field'],
            $data['aggregation'] ?? 'count',
            $x_axis,
            $data['color_scheme'] ?? 'default',
            $data['chart_sort'] ?? 'default',
            isset($data['show_total']) ? (int)$data['show_total'] : 0,
            $id,
            $org_id
        ]);
        echo json_encode(["success" => true, "id" => $id]);
    } else {
        // Insert new
        $global = isset($data['global']) ? (int)$data['global'] : 0;
        $org_id_for_insert = $global ? null : $org_id;

        // Calculate next sort order
        $sortStmt = $pdo->prepare("SELECT MAX(sort_order) as max_sort FROM custom_charts WHERE org_id = ?");
        $sortStmt->execute([$org_id]);
        $maxSort = $sortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'];
        $nextSort = ($maxSort !== null) ? $maxSort + 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO custom_charts (org_id, global, title, chart_type, data_field, aggregation, x_axis, color_scheme, chart_sort, show_total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $org_id_for_insert,
            $global,
            $data['title'],
            $data['chart_type'],
            $data['data_field'],
            $data['aggregation'] ?? 'count',
            $x_axis,
            $data['color_scheme'] ?? 'default',
            $data['chart_sort'] ?? 'default',
            isset($data['show_total']) ? (int)$data['show_total'] : 0,
            $nextSort
        ]);
        
        echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    error_log("save_chart.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage(), "details" => $e->getTraceAsString()]);
}
?>