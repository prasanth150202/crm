<?php
// api/external/delete_connection.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once '../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['org_id'], $data['conn_id'])) {
    echo json_encode(['error' => 'Missing org_id or conn_id']);
    exit;
}

$org_id = (int)$data['org_id'];
$conn_id = $data['conn_id'];

try {
    $pdo = getDb();
    $stmt = $pdo->prepare('DELETE FROM webhook_connections WHERE org_id = ? AND conn_id = ?');
    $stmt->execute([$org_id, $conn_id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Connection not found or already deleted']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'details' => $e->getMessage()]);
    exit;
}
