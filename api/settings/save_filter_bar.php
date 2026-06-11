<?php
// api/settings/save_filter_bar.php
// Saves the leads filter bar config (fields + operators + values) for the org
header("Content-Type: application/json");
require_once '../../includes/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$org_id = (int)$currentUser['org_id'];
$data   = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filter_bar']) || !is_array($data['filter_bar'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Sanitize: only keep expected keys; cast to object so JSON always encodes as {} not []
$inlineFilters    = (isset($data['filter_bar']['inlineFilters'])    && is_array($data['filter_bar']['inlineFilters']))    ? $data['filter_bar']['inlineFilters']    : [];
$inlineFilterMeta = (isset($data['filter_bar']['inlineFilterMeta']) && is_array($data['filter_bar']['inlineFilterMeta'])) ? $data['filter_bar']['inlineFilterMeta'] : [];

$filterBar = [
    'inlineFilters'    => empty($inlineFilters)    ? new stdClass() : $inlineFilters,
    'inlineFilterMeta' => empty($inlineFilterMeta) ? new stdClass() : $inlineFilterMeta,
];

try {
    $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch();

    $settings = ($org && $org['settings']) ? json_decode($org['settings'], true) : [];
    if (!is_array($settings)) $settings = [];

    $settings['filter_bar'] = $filterBar;

    $stmt = $pdo->prepare("UPDATE organizations SET settings = ? WHERE id = ?");
    $stmt->execute([json_encode($settings), $org_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save filter bar: ' . $e->getMessage()]);
}
