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

$data   = json_decode(file_get_contents('php://input'), true);
$org_id = (int)$_SESSION['org_id'];
$userId = (int)$_SESSION['user_id'];

$name = trim($data['name'] ?? '');
if (!$name) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO partners (org_id, name, company, email, phone, website, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $org_id,
        $name,
        trim($data['company'] ?? '') ?: null,
        trim($data['email']   ?? '') ?: null,
        trim($data['phone']   ?? '') ?: null,
        trim($data['website'] ?? '') ?: null,
        $userId,
    ]);

    $id = (int)$pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
