<?php
// save_receiver_config.php
// Saves field mapping configuration for webhook/post receivers

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}


$sources = [];
$source_names = $_POST['source_name'] ?? [];
if (!is_array($source_names) || count($source_names) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No sources provided']);
    exit;
}

foreach ($source_names as $idx => $name) {
    $name = trim($name);
    if (!$name) continue;
    $incoming = $_POST['incoming_' . ($idx+1)] ?? [];
    $lead_field = $_POST['lead_field_' . ($idx+1)] ?? [];
    $mappings = [];
    for ($i = 0; $i < count($incoming); $i++) {
        $in = trim($incoming[$i]);
        $lead = trim($lead_field[$i]);
        if ($in && $lead) {
            $mappings[] = ['incoming' => $in, 'lead_field' => $lead];
        }
    }
    if (!empty($mappings)) {
        $sources[] = [
            'name' => $name,
            'mappings' => $mappings
        ];
    }
}

if (empty($sources)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid sources provided']);
    exit;
}

$config_path = __DIR__ . '/receiver_field_map.json';
file_put_contents($config_path, json_encode($sources, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
