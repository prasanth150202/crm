<?php
// api/external/get_connections.php
header('Content-Type: application/json');
require_once '../../config/db.php';

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
if (!$org_id) {
    echo json_encode(['error' => 'Missing org_id']);
    exit;
}

try {
    $pdo = getDb();
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
