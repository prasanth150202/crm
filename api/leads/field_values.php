<?php
// api/leads/field_values.php
// Returns distinct non-empty values for a given lead field (standard or custom)
header("Content-Type: application/json");
require_once '../../includes/api_common.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$org_id = (int)($currentUser['org_id'] ?? 0);
$field  = isset($_GET['field']) ? trim($_GET['field']) : '';

if (!$field) {
    echo json_encode(["error" => "field is required"]);
    exit;
}

$allowedStdFields = [
    'name', 'first_name', 'last_name', 'title', 'company', 'email',
    'phone', 'city', 'state', 'country', 'zip_code', 'website',
    'address', 'source', 'stage_id', 'assigned_to'
];

try {
    $pm = getPermissionManager($pdo, $currentUser);
    $accessFilter = $pm->getLeadsFilter('l');

    $where  = $accessFilter['where'];
    $params = $accessFilter['params'];

    if (in_array($field, $allowedStdFields)) {
        // Standard column — whitelist already checked
        $col = "`$field`";
        $stmt = $pdo->prepare(
            "SELECT $col AS value, COUNT(*) AS count
             FROM leads l
             WHERE $where AND $col IS NOT NULL AND $col != ''
             GROUP BY $col
             ORDER BY count DESC, $col ASC
             LIMIT 200"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (preg_match('/^[a-zA-Z0-9_\- ]+$/', $field)) {
        // Custom JSON field
        $jsonRef = "JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$field\"'))";
        $stmt = $pdo->prepare(
            "SELECT $jsonRef AS value, COUNT(*) AS count
             FROM leads l
             WHERE $where
               AND JSON_EXTRACT(l.custom_data, '$.\"$field\"') IS NOT NULL
               AND $jsonRef NOT IN ('', 'null')
             GROUP BY value
             ORDER BY count DESC, value ASC
             LIMIT 200"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        echo json_encode(["error" => "Invalid field name"]);
        exit;
    }

    echo json_encode(['values' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed: ' . $e->getMessage()]);
}
