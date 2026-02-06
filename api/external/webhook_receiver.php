<?php
// webhook_receiver.php
// Receives webhook POST requests and uploads leads after matching fields

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST; // fallback for form-encoded
}

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing data']);
    exit;
}

// Load field mapping from config file if available
$config_path = __DIR__ . '/receiver_field_map.json';
if (file_exists($config_path)) {
    $field_map = json_decode(file_get_contents($config_path), true);
    if (!is_array($field_map)) {
        $field_map = [];
    }
} else {
    $field_map = [
        'name' => 'lead_name',
        'email' => 'lead_email',
        'phone' => 'lead_phone',
        // Add more mappings as needed
    ];
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

// TODO: Insert $lead into leads table (implement DB logic)
// Example placeholder:
// require_once '../../config/db.php';
// $db->insert('leads', $lead);

// For now, just return the mapped data
http_response_code(200);
echo json_encode(['success' => true, 'lead' => $lead]);
