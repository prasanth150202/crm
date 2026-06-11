<?php
// api/leads/sources.php
// Returns all distinct source values for the org's leads
header("Content-Type: application/json");
require_once '../../includes/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$org_id = (int)$currentUser['org_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT source FROM leads
         WHERE org_id = :org AND source IS NOT NULL AND source != ''
         ORDER BY source ASC"
    );
    $stmt->execute([':org' => $org_id]);
    $sources = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'sources' => $sources]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load sources: ' . $e->getMessage()]);
}
