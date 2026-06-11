<?php
// api/leads/list.php
header("Content-Type: application/json");
require_once '../../includes/api_common.php';

// Allow CORS (already handled in some envs but explicit here for safety)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// $currentUser and $pdo are provided by api_common.php
$org_id = (int)($currentUser['org_id'] ?? $_GET['org_id']);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

// Whitelist sort columns
$allowed_sorts = [
    'id', 'created_at', 'updated_at', 'name', 'first_name', 'last_name', 
    'title', 'company', 'email', 'phone', 'lead_value', 'source', 'stage_id',
    'address', 'city', 'state', 'country', 'zip_code', 'website', 'assigned_to'
];
$is_custom_sort = false;

if (!in_array($sort_by, $allowed_sorts)) {
    if (preg_match('/^[a-zA-Z0-9_\s\-]+$/', $sort_by)) {
        $is_custom_sort = true;
    } else {
        $sort_by = 'created_at';
    }
}

try {
    $pm = getPermissionManager($pdo, $currentUser);
    $accessFilter = $pm->getLeadsFilter('l');

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Define standard fields and their types for easier processing
    $standardTextFields = [
        'name', 'first_name', 'last_name', 'title', 'company', 'email', 'phone', 
        'address', 'city', 'state', 'country', 'zip_code', 'website', 'description'
    ];
    $standardExactFields = ['stage_id', 'assigned_to', 'source']; // source can be both but usually select
    
    $whereClauses = [$accessFilter['where']];
    $params = $accessFilter['params'];

    // Global Search
    if ($search) {
        $whereClauses[] = "(l.name LIKE :search1 OR l.company LIKE :search2 OR l.email LIKE :search3 OR l.phone LIKE :search4 OR l.city LIKE :search5 OR l.first_name LIKE :search6 OR l.last_name LIKE :search7 OR l.title LIKE :search8)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
        $params[':search4'] = "%$search%";
        $params[':search5'] = "%$search%";
        $params[':search6'] = "%$search%";
        $params[':search7'] = "%$search%";
        $params[':search8'] = "%$search%";
    }

    // Helper for applying filters (shared between main query and count)
    $applyFilter = function($column, $value, $op, $paramBase, $isExact = false) use (&$whereClauses, &$params) {
        if ($value === null || $value === '') return;

        $allowedOps = ['contains', 'not_contains', 'equals', 'not_equals', 'starts_with', 'ends_with'];
        if (!in_array($op, $allowedOps)) $op = $isExact ? 'equals' : 'contains';

        $pName = ":" . $paramBase . "_" . md5($column);

        switch ($op) {
            case 'equals':
                $whereClauses[] = "{$column} = {$pName}";
                $params[$pName] = $value;
                break;
            case 'not_equals':
                $whereClauses[] = "{$column} != {$pName}";
                $params[$pName] = $value;
                break;
            case 'not_contains':
                $whereClauses[] = "({$column} NOT LIKE {$pName} OR {$column} IS NULL)";
                $params[$pName] = '%' . $value . '%';
                break;
            case 'starts_with':
                $whereClauses[] = "{$column} LIKE {$pName}";
                $params[$pName] = $value . '%';
                break;
            case 'ends_with':
                $whereClauses[] = "{$column} LIKE {$pName}";
                $params[$pName] = '%' . $value;
                break;
            case 'contains':
            default:
                if ($isExact && ($op === 'contains')) {
                    $whereClauses[] = "{$column} = {$pName}";
                    $params[$pName] = $value;
                } else {
                    $whereClauses[] = "{$column} LIKE {$pName}";
                    $params[$pName] = '%' . $value . '%';
                }
                break;
        }
    };

    // 1. Standard Text Filters
    foreach ($standardTextFields as $field) {
        $val = isset($_GET[$field]) ? trim($_GET[$field]) : '';
        if ($val !== '') {
            $op = $_GET[$field . '_op'] ?? 'contains';
            $applyFilter("l.{$field}", $val, $op, "std");
        }
    }

    // 2. Standard Exact/Select Filters (supports single value or array for multi-select OR)
    foreach ($standardExactFields as $field) {
        $raw = $_GET[$field] ?? null;
        if (is_array($raw)) {
            $vals = array_values(array_filter(array_map('trim', $raw)));
            if (!empty($vals)) {
                // Special: assigned_to supports 'unassigned' → IS NULL
                if ($field === 'assigned_to') {
                    $hasUnassigned = in_array('unassigned', $vals);
                    $intVals = array_values(array_filter($vals, fn($v) => $v !== 'unassigned' && is_numeric($v)));
                    $conds = [];
                    if ($hasUnassigned) $conds[] = "l.assigned_to IS NULL";
                    if (!empty($intVals)) {
                        $ph = [];
                        foreach ($intVals as $i => $v) { $ph[] = ":msf_at_$i"; $params[":msf_at_$i"] = (int)$v; }
                        $conds[] = "l.assigned_to IN (" . implode(',', $ph) . ")";
                    }
                    if (!empty($conds)) $whereClauses[] = "(" . implode(' OR ', $conds) . ")";
                } else {
                    $placeholders = [];
                    foreach ($vals as $i => $v) {
                        $pKey = ":msf_{$field}_{$i}";
                        $placeholders[] = $pKey;
                        $params[$pKey] = $v;
                    }
                    $whereClauses[] = "l.`{$field}` IN (" . implode(',', $placeholders) . ")";
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $op = $_GET[$field . '_op'] ?? 'equals';
            $applyFilter("l.{$field}", trim($raw), $op, "std", true);
        }
    }

    // 2. Numeric Range (lead_value)
    if (isset($_GET['lead_value_min']) && $_GET['lead_value_min'] !== '') {
        $whereClauses[] = "l.lead_value >= :lv_min";
        $params[':lv_min'] = (float)$_GET['lead_value_min'];
    }
    if (isset($_GET['lead_value_max']) && $_GET['lead_value_max'] !== '') {
        $whereClauses[] = "l.lead_value <= :lv_max";
        $params[':lv_max'] = (float)$_GET['lead_value_max'];
    }

    // 3. Date Ranges
    foreach (['created_at', 'updated_at'] as $dateField) {
        if (isset($_GET[$dateField . '_from']) && $_GET[$dateField . '_from'] !== '') {
            $whereClauses[] = "DATE(l.{$dateField}) >= :{$dateField}_from";
            $params[":{$dateField}_from"] = $_GET[$dateField . '_from'];
        }
        if (isset($_GET[$dateField . '_to']) && $_GET[$dateField . '_to'] !== '') {
            $whereClauses[] = "DATE(l.{$dateField}) <= :{$dateField}_to";
            $params[":{$dateField}_to"] = $_GET[$dateField . '_to'];
        }
        // Header filter (exact substring match for dates)
        if (isset($_GET[$dateField]) && $_GET[$dateField] !== '' && !isset($_GET[$dateField . '_from'])) {
            $whereClauses[] = "l.{$dateField} LIKE :{$dateField}_h";
            $params[":{$dateField}_h"] = '%' . $_GET[$dateField] . '%';
        }
    }

    // 4. Custom Field Filters
    $customFilters = [];       // scalar values  → existing op-based logic
    $customFilterIN = [];      // array values   → IN (...) logic (facet multi-select)
    if (isset($_GET['custom_filters']) && is_array($_GET['custom_filters'])) {
        foreach ($_GET['custom_filters'] as $key => $value) {
            if (is_array($value)) {
                // Facet multi-select: custom_filters[field][]=val1&custom_filters[field][]=val2
                $vals = array_values(array_filter(array_map('trim', $value), fn($v) => $v !== ''));
                if (!empty($vals)) $customFilterIN[$key] = $vals;
            } elseif ($value !== '' && is_scalar($value)) {
                $customFilters[$key] = $value;
            }
        }
    }

    // Apply IN filters for custom facet multi-select
    foreach ($customFilterIN as $fieldKey => $vals) {
        if (!preg_match('/^[a-zA-Z0-9_\- ]+$/', $fieldKey)) continue;
        $jsonRef = "JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldKey\"'))";
        $ph = [];
        foreach (array_values($vals) as $i => $v) {
            $p = ':cfin_' . md5($fieldKey) . "_$i";
            $ph[] = $p;
            $params[$p] = $v;
        }
        $whereClauses[] = "$jsonRef IN (" . implode(',', $ph) . ")";
    }
    // Also support custom_FieldKey legacy format
    foreach ($_GET as $key => $value) {
        if (strpos($key, 'custom_') === 0 && $key !== 'custom_filters' && $value !== '' && !is_array($value)) {
            $customFilters[substr($key, 7)] = $value;
        }
    }

    foreach ($customFilters as $fieldKey => $value) {
        $safeParam = ":cf_" . md5($fieldKey);
        
        // Handle range suffixes for custom fields
        if (preg_match('/^(.+)_min$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $whereClauses[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) >= {$safeParam}";
            $params[$safeParam] = (float)$value;
        } elseif (preg_match('/^(.+)_max$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $whereClauses[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) <= {$safeParam}";
            $params[$safeParam] = (float)$value;
        } elseif (preg_match('/^(.+)_from$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $whereClauses[] = "DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) >= {$safeParam}";
            $params[$safeParam] = $value;
        } elseif (preg_match('/^(.+)_to$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $whereClauses[] = "DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) <= {$safeParam}";
            $params[$safeParam] = $value;
        } else {
            // Standard custom field text filter with operator support
            $op = $_GET[$fieldKey . '_op'] ?? 'contains';
            $allowedOps = ['contains', 'not_contains', 'equals', 'not_equals', 'starts_with', 'ends_with'];
            if (!in_array($op, $allowedOps)) $op = 'contains';

            $jsonRef = "JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldKey\"'))";

            switch ($op) {
                case 'equals':
                    $whereClauses[] = "{$jsonRef} = {$safeParam}";
                    $params[$safeParam] = $value;
                    break;
                case 'not_equals':
                    $whereClauses[] = "{$jsonRef} != {$safeParam}";
                    $params[$safeParam] = $value;
                    break;
                case 'not_contains':
                    $whereClauses[] = "({$jsonRef} NOT LIKE {$safeParam} OR {$jsonRef} IS NULL)";
                    $params[$safeParam] = '%' . $value . '%';
                    break;
                case 'starts_with':
                    $whereClauses[] = "{$jsonRef} LIKE {$safeParam}";
                    $params[$safeParam] = $value . '%';
                    break;
                case 'ends_with':
                    $whereClauses[] = "{$jsonRef} LIKE {$safeParam}";
                    $params[$safeParam] = '%' . $value;
                    break;
                case 'contains':
                default:
                    $whereClauses[] = "{$jsonRef} LIKE {$safeParam}";
                    $params[$safeParam] = '%' . $value . '%';
                    break;
            }
        }
    }

    // 5. is_empty / is_not_empty operators (no value — sent via inline_ops[field]=op)
    if (isset($_GET['inline_ops']) && is_array($_GET['inline_ops'])) {
        foreach ($_GET['inline_ops'] as $rawField => $op) {
            $op = trim($op);
            if (!in_array($op, ['is_empty', 'is_not_empty'])) continue;
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $rawField);
            if ($field === '') continue;
            if (in_array($field, $standardTextFields)) {
                if ($op === 'is_empty') {
                    $whereClauses[] = "(l.`{$field}` IS NULL OR l.`{$field}` = '')";
                } else {
                    $whereClauses[] = "(l.`{$field}` IS NOT NULL AND l.`{$field}` != '')";
                }
            } else {
                // Custom JSON field
                $jsonRef = "JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$field\"'))";
                if ($op === 'is_empty') {
                    $whereClauses[] = "($jsonRef IS NULL OR $jsonRef = '' OR $jsonRef = 'null')";
                } else {
                    $whereClauses[] = "($jsonRef IS NOT NULL AND $jsonRef != '' AND $jsonRef != 'null')";
                }
            }
        }
    }

    $whereSQL = implode(" AND ", $whereClauses);

    // Main Query
    $sql = "SELECT l.*, u.email as owner_email, 
                   asgn.full_name as assigned_to_name,
                   (SELECT COUNT(*) FROM leads l2 WHERE l2.org_id = l.org_id AND l2.id <= l.id) as seq_num,
                   CASE 
                       WHEN l.stage_id = 'new' THEN 'NEW'
                       WHEN l.stage_id = 'contacted' THEN 'CONTACTED'
                       WHEN l.stage_id = 'qualified' THEN 'QUALIFIED'
                       WHEN l.stage_id = 'proposal' THEN 'PROPOSAL'
                       WHEN l.stage_id = 'won' THEN 'CLOSED WON'
                       WHEN l.stage_id = 'lost' THEN 'LOST'
                       ELSE CONCAT('Stage ', l.stage_id)
                   END as stage_name
            FROM leads l 
            LEFT JOIN users u ON l.owner_id = u.id 
            LEFT JOIN users asgn ON l.assigned_to = asgn.id
            WHERE " . $whereSQL;

    // Sorting
    if ($is_custom_sort) {
        $fieldName = str_replace('"', '', $sort_by);
        $sql .= " ORDER BY JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) $sort_order";
    } else {
        $sort_by = str_replace('`', '', $sort_by);
        $sql .= " ORDER BY l.`$sort_by` $sort_order";
    }

    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) || is_float($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count Query (Matches exactly)
    $count_sql = "SELECT COUNT(*) FROM leads l WHERE " . $whereSQL;
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value, is_int($value) || is_float($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total = $count_stmt->fetchColumn();

    // Preparation for response
    foreach ($leads as &$lead) {
        if ($lead['custom_data']) {
            $customData = json_decode($lead['custom_data'], true);
            $lead['custom_data'] = is_array($customData) ? $customData : [];
        } else {
            $lead['custom_data'] = [];
        }
        
        // Sanitize for JSON output
        foreach ($lead as $key => $value) {
            if ($key !== 'custom_data' && is_string($value)) {
                $lead[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
    }

    echo json_encode([
        "data" => $leads,
        "meta" => [
            "total" => (int)$total,
            "limit" => (int)$limit,
            "offset" => (int)$offset
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch leads: " . $e->getMessage()]);
}
