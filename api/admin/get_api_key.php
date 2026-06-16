<?php
// api/admin/get_api_key.php
ini_set('display_errors', 0);
error_reporting(0);
require_once '../../config/db.php';
require_once '../../config/security.php';

Security::cors(getenv('CORS_ALLOWED_ORIGINS'));
Security::secureSession();

header("Content-Type: application/json");

$isAdmin = isset($_SESSION['user_id']) && (in_array($_SESSION['role'] ?? '', ['admin', 'owner']) || !empty($_SESSION['is_super_admin']));
if (!$isAdmin) {
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
    $pdo = getDb();
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
