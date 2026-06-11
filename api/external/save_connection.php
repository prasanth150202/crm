<?php
// api/external/save_connection.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['name'])) {
    echo json_encode(['error' => 'Missing connection name']);
    exit;
}

try {
    // Use session user's org_id for security
    $pdo = getDb();
    $user_id = $_SESSION['user_id'];
    $stmt_check = $pdo->prepare('SELECT org_id FROM users WHERE id = ?');
    $stmt_check->execute([$user_id]);
    $userOrg = (int)$stmt_check->fetchColumn();

    // Allow org_id from request but validate ownership
    $requested_org = isset($data['org_id']) ? (int)$data['org_id'] : $userOrg;
    if ($requested_org != $userOrg) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $org_id = $userOrg;
    $name   = trim($data['name']);

    $pdo->beginTransaction();

    // Lock rows for this org to avoid race condition
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(conn_id_num), 0) + 1 AS next_num
         FROM webhook_connections
         WHERE org_id = ?
         FOR UPDATE'
    );
    $stmt->execute([$org_id]);
    $next_num = (int)$stmt->fetchColumn();

    // Generate SAFE public conn_id
    $conn_id = bin2hex(random_bytes(16)); // 32-char opaque ID

    $stmt = $pdo->prepare(
        'INSERT INTO webhook_connections (org_id, conn_id, conn_id_num, name)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$org_id, $conn_id, $next_num, $name]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'conn_id' => $conn_id,
        'name'    => $name
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Show full error for debugging
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'details' => $e->getMessage()]);
    exit;
}
