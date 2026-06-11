<?php
// api/leads/delete.php
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

if (!isset($data['id']) || !isset($data['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: id, org_id"]);
    exit;
}

$id = (int)$data['id'];
$org_id = (int)$data['org_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND org_id = ?");
    $stmt->execute([$id, $org_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Lead deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "Lead not found or already deleted"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to delete lead: " . $e->getMessage()]);
}

