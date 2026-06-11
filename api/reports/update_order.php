<?php
// api/reports/update_order.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing order data"]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE custom_charts SET sort_order = ? WHERE id = ? AND org_id = ?");
    
    foreach ($data['order'] as $item) {
        $stmt->execute([$item['order'], $item['id'], $org_id]);
    }
    
    $pdo->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
