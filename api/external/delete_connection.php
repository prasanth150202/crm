<?php
// api/external/delete_connection.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['conn_id'])) {
    echo json_encode(['error' => 'Missing conn_id']);
    exit;
}

try {
    // Validate org ownership
    $pdo = getDb();
    $user_id = $_SESSION['user_id'];
    $stmt_auth = $pdo->prepare('SELECT org_id FROM users WHERE id = ?');
    $stmt_auth->execute([$user_id]);
    $userOrg = (int)$stmt_auth->fetchColumn();

    $org_id = isset($data['org_id']) ? (int)$data['org_id'] : $userOrg;
    if ($org_id != $userOrg) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    $conn_id = $data['conn_id'];

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
