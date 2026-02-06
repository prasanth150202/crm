<?php
// api/external/auth.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['api_key'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing api_key"]);
    exit;
}

try {
    // Auto-detect organization from API key
    $stmt = $pdo->prepare("SELECT id, name FROM organizations WHERE api_key = ?");
    $stmt->execute([$input['api_key']]);
    $org = $stmt->fetch();

    if (!$org) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid API key or organization"]);
        exit;
    }

    // Generate session token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare("INSERT INTO api_sessions (token, org_id, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$token, $org['id'], $expires_at]);

    echo json_encode([
        "success" => true,
        "token" => $token,
        "expires_at" => $expires_at,
        "organization" => $org['name']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Authentication failed: " . $e->getMessage()]);
}