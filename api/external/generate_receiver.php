<?php
// generate_receiver.php
// Dynamically generates a webhook receiver PHP file for a given source

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

if ($argc < 2) {
    exit("Usage: php generate_receiver.php <source_name>\n");
}

$source = preg_replace('/\W+/', '_', strtolower($argv[1]));
$filename = __DIR__ . "/webhook_receiver_{$source}.php";

$template = <<<'PHP'
<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing data']);
    exit;
}
// Load field mapping for this source
$config_path = __DIR__ . '/receiver_field_map.json';
$field_map = [];
if (file_exists($config_path)) {
    $sources = json_decode(file_get_contents($config_path), true);
    if (is_array($sources)) {
        foreach ($sources as $src) {
            if (strtolower(preg_replace('/\W+/', '_', $src['name'])) === '%SOURCE%') {
                foreach ($src['mappings'] as $map) {
                    $field_map[$map['incoming']] = $map['lead_field'];
                }
            }
        }
    }
}
$lead = [];
foreach ($field_map as $incoming => $lead_field) {
    if (isset($data[$incoming])) {
        $lead[$lead_field] = $data[$incoming];
    }
}
if (empty($lead)) {
    http_response_code(400);
    echo json_encode(['error' => 'No matching fields found']);
    exit;
}
// TODO: Insert $lead into leads table
http_response_code(200);
echo json_encode(['success' => true, 'lead' => $lead]);
PHP;

file_put_contents($filename, str_replace('%SOURCE%', $source, $template));
echo "Generated: $filename\n";
