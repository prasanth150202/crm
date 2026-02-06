<?php
// api/external/get_mapping.php
header('Content-Type: application/json');
require_once '../../config/db.php';

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$conn_id = isset($_GET['conn_id']) ? trim($_GET['conn_id']) : '';

if (!$org_id || !$conn_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing org_id or conn_id']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT mapping
         FROM webhook_field_mappings
         WHERE org_id = ? AND conn_id = ?'
    );
    $stmt->execute([$org_id, $conn_id]);
    $mapping = $stmt->fetchColumn();
    $mapping = $mapping ? json_decode($mapping, true) : [];

    echo json_encode(['mapping' => $mapping]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load mapping', 'details' => $e->getMessage()]);
}
?>