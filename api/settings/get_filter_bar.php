<?php
// api/settings/get_filter_bar.php
// Returns the saved leads filter bar config for the current user's org
header("Content-Type: application/json");
require_once '../../includes/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$org_id = (int)$currentUser['org_id'];

try {
    $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch();

    $settings  = ($org && $org['settings']) ? json_decode($org['settings'], true) : [];
    $filterBar = (is_array($settings) && isset($settings['filter_bar'])) ? $settings['filter_bar'] : null;

    echo json_encode(['success' => true, 'filter_bar' => $filterBar]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load filter bar: ' . $e->getMessage()]);
}
