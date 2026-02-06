<?php
// load_receiver_config.php
// Loads all sources and their field mappings for the config page

header('Content-Type: application/json');

$config_path = __DIR__ . '/receiver_field_map.json';
if (!file_exists($config_path)) {
    echo json_encode(['sources' => []]);
    exit;
}

$config = json_decode(file_get_contents($config_path), true);
if (!is_array($config)) {
    echo json_encode(['sources' => []]);
    exit;
}

echo json_encode(['sources' => $config]);
