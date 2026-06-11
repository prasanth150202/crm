<?php
// api/reports/delete_chart.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
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
if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing chart id"]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM custom_charts WHERE id = ? AND org_id = ?");
    $stmt->execute([$id, $org_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Chart not found or not authorized"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>