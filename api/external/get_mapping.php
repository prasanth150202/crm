<?php
// api/external/get_mapping.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

// Validate user owns this org
try {
    $pdo = getDb();
    $user_id = $_SESSION['user_id'];
    $stmt_auth = $pdo->prepare('SELECT org_id FROM users WHERE id = ?');
    $stmt_auth->execute([$user_id]);
    $userOrg = (int)$stmt_auth->fetchColumn();

    $org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : $userOrg;
    if ($org_id != $userOrg) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Auth validation failed', 'details' => $e->getMessage()]);
    exit;
}

$conn_id = isset($_GET['conn_id']) ? trim($_GET['conn_id']) : '';

if (!$org_id || !$conn_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing org_id or conn_id']);
    exit;
}

if ($org_id != $userOrg) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
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