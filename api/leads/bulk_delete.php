<?php
// api/leads/bulk_delete.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['ids']) || !is_array($data['ids']) || !isset($data['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: ids[], org_id"]);
    exit;
}

$ids = array_filter(array_map('intval', $data['ids']));
$org_id = (int)$data['org_id'];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(["error" => "No valid ids provided"]);
    exit;
}

try {
    // Build placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $params[] = $org_id;

    $stmt = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders) AND org_id = ?");
    $stmt->execute($params);

    echo json_encode([
        "success" => true,
        "deleted" => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to bulk delete: " . $e->getMessage()]);
}

