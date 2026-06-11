<?php
// api/leads/import_history.php
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;

if (!$org_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT ij.*, u.email as user_email FROM import_jobs ij LEFT JOIN users u ON ij.user_id = u.id WHERE ij.org_id = ? ORDER BY ij.created_at DESC LIMIT 50");
    $stmt->execute([$org_id]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "jobs" => $jobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch import history: " . $e->getMessage()]);
}
