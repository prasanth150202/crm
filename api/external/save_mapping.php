<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// save_mapping.php

header('Content-Type: application/json');

// 1. Read raw JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// 2. Validate JSON
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// 3. Extract values
$org_id  = isset($data['org_id']) ? (int)$data['org_id'] : 0;
$conn_id = isset($data['conn_id']) ? trim($data['conn_id']) : '';
$mapping = $data['mapping'] ?? null;

// 4. Validate required data
if (!$org_id || !$conn_id || !is_array($mapping) || empty($mapping)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing org_id, conn_id, or mapping']);
    exit;
}


// 5. Save mapping to DB
require_once '../../config/db.php';
try {
    if (function_exists('getDb')) {
        $pdo = getDb();
    }
    $stmt = $pdo->prepare('INSERT INTO webhook_field_mappings (org_id, conn_id, mapping) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE mapping = VALUES(mapping), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$org_id, $conn_id, json_encode($mapping)]);
    $db_saved = true;
} catch (Exception $e) {
    $db_saved = false;
    $db_error = $e->getMessage();
    // Show full error for debugging
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'details' => $db_error]);
    exit;
}

// Also save as file for backward compatibility
$map_dir = __DIR__ . '/field_mappings';
if (!is_dir($map_dir)) {
    mkdir($map_dir, 0777, true);
}
$file = $map_dir . "/map_{$org_id}_{$conn_id}.json";
file_put_contents($file, json_encode($mapping, JSON_PRETTY_PRINT));

// 6. Success
echo json_encode([
    'success' => $db_saved,
    'file' => basename($file),
    'db' => $db_saved ? 'saved' : 'error',
    'db_error' => $db_saved ? null : $db_error
]);
