<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// api/external/save_connection.php
header('Content-Type: application/json');
require_once '../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['org_id'], $data['name'])) {
    echo json_encode(['error' => 'Missing org_id or name']);
    exit;
}

$org_id = (int)$data['org_id'];
$name   = trim($data['name']);

try {
    $pdo = getDb();
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
