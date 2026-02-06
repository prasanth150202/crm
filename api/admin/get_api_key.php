<?php
// api/admin/get_api_key.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: null");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Admin access required. Please login first."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : (int)$_SESSION['org_id'];

try {
    $stmt = $pdo->prepare("SELECT id, name, api_key FROM organizations WHERE id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch();

    if (!$org) {
        http_response_code(404);
        echo json_encode(["error" => "Organization not found"]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "org_id" => $org['id'],
        "organization" => $org['name'],
        "api_key" => $org['api_key']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch API key: " . $e->getMessage()]);
}
