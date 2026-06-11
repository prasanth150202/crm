<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$org_id    = (int)$_SESSION['org_id'];
$partnerId = (int)($data['partner_id'] ?? 0);

if (!$partnerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'partner_id required']);
    exit;
}

try {
    // Verify partner belongs to this org before deleting
    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ? AND org_id = ?");
    $stmt->execute([$partnerId, $org_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Partner not found']);
        exit;
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
