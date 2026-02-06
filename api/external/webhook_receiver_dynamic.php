<?php
header('Content-Type: application/json');
require_once '../../config/db.php';

/* ------------------ INPUT ------------------ */
$org_id  = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$conn_id = $_GET['conn_id'] ?? '';


// Accept API key from either Authorization header or query parameter
$api_key = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.*)/', $auth, $m)) {
    $api_key = trim($m[1]);
} elseif (isset($_GET['api_key'])) {
    $api_key = trim($_GET['api_key']);
}
if (!$api_key) {
    http_response_code(401);
    exit(json_encode(['error' => 'Missing API key']));
}

if (!$org_id || !$conn_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing org_id or conn_id']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed']));
}

/* ------------------ PAYLOAD ------------------ */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

if (!is_array($data)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid payload']));
}


try {
    $pdo = getDb();
} catch (Throwable $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]));
}

/* ------------------ VALIDATE ORG + CONN ------------------ */
$stmt = $pdo->prepare(
    'SELECT c.id
     FROM webhook_connections c
     JOIN organizations o ON o.id = c.org_id
     WHERE c.org_id = ? AND c.conn_id = ? AND o.api_key = ?'
);
$stmt->execute([$org_id, $conn_id, $api_key]);

if (!$stmt->fetch()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid credentials']));
}

/* ------------------ LOG PAYLOAD ------------------ */



// Always write payload to a file for debugging
$logDir = __DIR__ . '/../../uploads/webhook_debug_logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/payload_' . date('Ymd_His') . '_' . uniqid() . '.log';
file_put_contents($logFile, json_encode([
    'datetime' => date('c'),
    'org_id' => $org_id,
    'conn_id' => $conn_id,
    'api_key' => $api_key,
    'data' => $data,
    'raw' => $raw,
    'server' => $_SERVER
], JSON_PRETTY_PRINT));

// Save test payload for mapping UI
$testPayloadDir = __DIR__ . '/test_payloads';
if (!is_dir($testPayloadDir)) {
    @mkdir($testPayloadDir, 0777, true);
}
$testPayloadFile = $testPayloadDir . "/test_{$org_id}_conn_{$conn_id}.json";
file_put_contents($testPayloadFile, json_encode($data, JSON_PRETTY_PRINT));

try {
    $stmt = $pdo->prepare(
        'INSERT INTO webhook_payload_logs (org_id, conn_id, payload)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$org_id, $conn_id, json_encode($data)]);
} catch (Throwable $e) {
    // Log DB error to file as well
    file_put_contents($logFile . '.dberror', $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Payload log failed', 'details' => $e->getMessage()]));
}

/* ------------------ LOAD MAPPING ------------------ */

// Use correct table for mappings
$stmt = $pdo->prepare(
    'SELECT mapping
     FROM webhook_field_mappings
     WHERE org_id = ? AND conn_id = ?'
);
$stmt->execute([$org_id, $conn_id]);
$mapping = json_decode($stmt->fetchColumn(), true);

if (!$mapping) {
    echo json_encode(['success' => true, 'note' => 'No mapping yet']);
    exit;
}

/* ------------------ APPLY MAPPING ------------------ */
$allowedStandard = ['name','email','phone','company','source','stage_id','lead_value','title']; // Only columns that exist in leads table
$standard = [];
$custom   = [];

foreach ($mapping as $field => $sources) {
    $value = '';
    foreach ($sources as $source) {
        if (preg_match('/^\{\{(.+)\}\}$/', $source, $m)) {
            $key = $m[1];
            $payloadValue = $data[$key] ?? null;
            if ($payloadValue !== null) {
                if (is_scalar($payloadValue)) {
                    $value .= $payloadValue;
                } else {
                    // Handle non-scalar (e.g., arrays/objects) by JSON encoding
                    $value .= json_encode($payloadValue);
                }
            }
        } else {
            $value .= $source;
        }
    }
    $value = trim($value); // Trim any leading/trailing whitespace from concatenation
    if (!empty($value)) { // Only assign non-empty values
        if (strpos($field, 'custom_') === 0) {
            // Remove 'custom_' prefix for custom fields
            $customFieldName = preg_replace('/^custom_/', '', $field);
            $custom[$customFieldName] = $value;
        } elseif (in_array($field, $allowedStandard)) {
            // Treat allowed standard fields as standard
            $standard[$field] = $value;
        } else {
            // Treat other fields (like title, lead_value) as custom
            $custom[$field] = $value;
        }
    }
}

/* ------------------ INSERT LEAD ------------------ */
try {
    $cols = array_keys($standard);
    $params = $standard;

    $cols[] = 'org_id';
    $params['org_id'] = $org_id;

    $cols[] = 'custom_data';
    $params['custom_data'] = json_encode($custom);

    $placeholders = array_map(fn($c) => ':' . $c, $cols);

    // Check if lead already exists (by email, for example)
    $existingLeadId = null;
    if (!empty($standard['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM leads WHERE org_id = ? AND email = ?');
        $stmt->execute([$org_id, $standard['email']]);
        $existingLeadId = $stmt->fetchColumn();
    }

    if ($existingLeadId) {
        // Update existing lead
        $setParts = array_map(fn($c) => "$c = :$c", $cols);
        $sql = 'UPDATE leads SET ' . implode(', ', $setParts) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
        $params['id'] = $existingLeadId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'action' => 'updated', 'lead_id' => $existingLeadId]);
    } else {
        // Insert new lead
        $sql = 'INSERT INTO leads (' . implode(',', $cols) . ')
                VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leadId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'action' => 'inserted', 'lead_id' => $leadId]);
    }
} catch (Throwable $e) {
    // Log the error to the payload log file for debugging
    file_put_contents($logFile . '.leaderror', $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Lead insert/update failed', 'details' => $e->getMessage()]);
}