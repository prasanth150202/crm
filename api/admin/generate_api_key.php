<?php
// api/admin/generate_api_key.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: null");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Admin access required. Please login first."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Allow admin to generate a key for a specific org (defaults to their current org)
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$targetOrgId = isset($input['org_id']) ? (int)$input['org_id'] : (int)$_SESSION['org_id'];

try {
    // Validate that the target org exists
    $orgStmt = $pdo->prepare("SELECT id, name FROM organizations WHERE id = ?");
    $orgStmt->execute([$targetOrgId]);
    $org = $orgStmt->fetch();

    if (!$org) {
        http_response_code(404);
        echo json_encode(["error" => "Organization not found"]);
        exit;
    }

    $api_key = bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare("UPDATE organizations SET api_key = ? WHERE id = ?");
    $stmt->execute([$api_key, $targetOrgId]);
    
    echo json_encode([
        "success" => true,
        "api_key" => $api_key,
        "org_id" => $targetOrgId,
        "organization" => $org['name'],
        "message" => "API key generated successfully"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to generate API key: " . $e->getMessage()]);
}