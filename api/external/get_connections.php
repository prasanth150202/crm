<?php
// api/external/get_connections.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

// Allow org_id from query param, but validate user belongs to that org
try {
    $pdo = getDb();
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT org_id FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $userOrg = $stmt->fetchColumn();

    $org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : (int)$userOrg;
    if (!$org_id || $org_id != $userOrg) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Auth validation failed', 'details' => $e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT conn_id, conn_id_num, name
         FROM webhook_connections
         WHERE org_id = ?
         ORDER BY conn_id_num ASC'
    );
    $stmt->execute([$org_id]);

    echo json_encode([
        'success' => true,
        'connections' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => 'DB error']);
}
