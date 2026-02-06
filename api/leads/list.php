<?php
// api/leads/list.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

if (!isset($_GET['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}

$org_id = (int)$_GET['org_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

// Whitelist sort columns to prevent SQL injection
$allowed_sorts = ['id', 'created_at', 'updated_at', 'name', 'company', 'last_contacted_at', 'email', 'phone', 'lead_value', 'source', 'stage_id'];
$is_custom_sort = false;

if (!in_array($sort_by, $allowed_sorts)) {
    // If not in standard list, it's likely a custom field
    // We sanitize it to prevent injection: only allow alphanumeric, spaces, and underscores
    if (preg_match('/^[a-zA-Z0-9_\s\-]+$/', $sort_by)) {
        $is_custom_sort = true;
    } else {
        $sort_by = 'created_at';
    }
}

try {
    // Filter Parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $stage_id = isset($_GET['stage_id']) ? $_GET['stage_id'] : '';
    $source = isset($_GET['source']) ? $_GET['source'] : '';

    // Advanced filter parameters
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';
    $title = isset($_GET['title']) ? trim($_GET['title']) : '';
    $company = isset($_GET['company']) ? trim($_GET['company']) : '';
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    $phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
    $lead_value_min = isset($_GET['lead_value_min']) ? (float)$_GET['lead_value_min'] : '';
    $lead_value_max = isset($_GET['lead_value_max']) ? (float)$_GET['lead_value_max'] : '';
    $created_at_from = isset($_GET['created_at_from']) ? $_GET['created_at_from'] : '';
    $created_at_to = isset($_GET['created_at_to']) ? $_GET['created_at_to'] : '';
    $updated_at_from = isset($_GET['updated_at_from']) ? $_GET['updated_at_from'] : '';
    $updated_at_to = isset($_GET['updated_at_to']) ? $_GET['updated_at_to'] : '';

    // Header filters for dates
    $created_at = isset($_GET['created_at']) ? trim($_GET['created_at']) : '';
    $updated_at = isset($_GET['updated_at']) ? trim($_GET['updated_at']) : '';

    require_once '../../config/permissions.php';
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    // Get current user for permissions
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    $pm = getPermissionManager($pdo, $currentUser);
    $filter = $pm->getLeadsFilter('l');

    // Build Query with stage names (fallback if stages table doesn't exist)
    $sql = "SELECT l.*, u.email as owner_email, 
                   asgn.full_name as assigned_to_name,
                   (SELECT COUNT(*) FROM leads l2 WHERE l2.org_id = l.org_id AND l2.id <= l.id) as seq_num,
                   CASE 
                       WHEN l.stage_id = 'new' THEN 'NEW'
                       WHEN l.stage_id = 'contacted' THEN 'CONTACTED'
                       WHEN l.stage_id = 'qualified' THEN 'QUALIFIED'
                       WHEN l.stage_id = 'proposal' THEN 'PROPOSAL'
                       WHEN l.stage_id = 'won' THEN 'CLOSED WON'
                       ELSE CONCAT('Stage ', l.stage_id)
                   END as stage_name
            FROM leads l 
            LEFT JOIN users u ON l.owner_id = u.id 
            LEFT JOIN users asgn ON l.assigned_to = asgn.id
            WHERE " . $filter['where'];
    
    $params = $filter['params'];

    if ($search) {
        $sql .= " AND (l.name LIKE :search1 OR l.company LIKE :search2 OR l.email LIKE :search3 OR l.phone LIKE :search4)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
        $params[':search4'] = "%$search%";
    }

    if ($stage_id) {
        $sql .= " AND l.stage_id = :stage_id";
        $params[':stage_id'] = $stage_id;
    }

    if ($source) {
        $sql .= " AND l.source = :source";
        $params[':source'] = $source;
    }

    // Advanced filters
    if ($name) {
        $sql .= " AND l.name LIKE :name";
        $params[':name'] = "%$name%";
    }

    if ($title) {
        $sql .= " AND l.title LIKE :title";
        $params[':title'] = "%$title%";
    }

    if ($company) {
        $sql .= " AND l.company LIKE :company";
        $params[':company'] = "%$company%";
    }

    if ($email) {
        $sql .= " AND l.email LIKE :email";
        $params[':email'] = "%$email%";
    }

    if ($phone) {
        $sql .= " AND l.phone LIKE :phone";
        $params[':phone'] = "%$phone%";
    }

    if ($lead_value_min !== '') {
        $sql .= " AND l.lead_value >= :lead_value_min";
        $params[':lead_value_min'] = $lead_value_min;
    }

    if ($lead_value_max !== '') {
        $sql .= " AND l.lead_value <= :lead_value_max";
        $params[':lead_value_max'] = $lead_value_max;
    }

    if ($created_at_from) {
        $sql .= " AND DATE(l.created_at) >= :created_at_from";
        $params[':created_at_from'] = $created_at_from;
    }

    if ($created_at_to) {
        $sql .= " AND DATE(l.created_at) <= :created_at_to";
        $params[':created_at_to'] = $created_at_to;
    }

    if ($updated_at_from) {
        $sql .= " AND DATE(l.updated_at) >= :updated_at_from";
        $params[':updated_at_from'] = $updated_at_from;
    }

    if ($updated_at_to) {
        $sql .= " AND DATE(l.updated_at) <= :updated_at_to";
        $params[':updated_at_to'] = $updated_at_to;
    }

    if ($created_at) {
        $sql .= " AND l.created_at LIKE :created_at_h";
        $params[':created_at_h'] = "%$created_at%";
    }

    if ($updated_at) {
        $sql .= " AND l.updated_at LIKE :updated_at_h";
        $params[':updated_at_h'] = "%$updated_at%";
    }

    // Handle custom field filters (Unified Logic)
    $customFilters = [];
    
    // 1. Support legacy/direct "custom_Key"
    foreach ($_GET as $key => $value) {
        // Exclude 'custom_filters' to prevent processing the array parameter as a legacy field
        if (strpos($key, 'custom_') === 0 && $key !== 'custom_filters' && !empty($value) && !is_array($value)) {
            $fieldKey = substr($key, 7);
            $customFilters[$fieldKey] = $value;
        }
    }

    // 2. Support new "custom_filters[Key]" array
    if (isset($_GET['custom_filters']) && is_array($_GET['custom_filters'])) {
        foreach ($_GET['custom_filters'] as $key => $value) {
            if (!empty($value) && is_scalar($value)) {
                $customFilters[$key] = $value;
            }
        }
    }

    foreach ($customFilters as $fieldKey => $value) {
        // Generate a safe parameter name to handle fields with spaces (e.g. "Lead type")
        $safeParam = ":cf_" . md5($fieldKey);
        
        if (preg_match('/^(.+)_min$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $sql .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) >= {$safeParam}_min";
            $params["{$safeParam}_min"] = (float)$value;
        } elseif (preg_match('/^(.+)_max$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $sql .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) <= {$safeParam}_max";
            $params["{$safeParam}_max"] = (float)$value;
        } elseif (preg_match('/^(.+)_from$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $sql .= " AND DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) >= {$safeParam}_from";
            $params["{$safeParam}_from"] = $value;
        } elseif (preg_match('/^(.+)_to$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $sql .= " AND DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) <= {$safeParam}_to";
            $params["{$safeParam}_to"] = $value;
        } else {
            $fieldName = $fieldKey;
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) LIKE {$safeParam}";
            $params[$safeParam] = "%$value%";
        }
    }

    // Sort order
    if ($is_custom_sort) {
        // Use JSON_EXTRACT for custom fields. We also wrap in COALESCE to handle nulls
        $sql .= " ORDER BY JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$sort_by\"')) $sort_order";
    } else {
        $sql .= " ORDER BY l.$sort_by $sort_order";
    }
    
    // Pagination
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    
    // Bind all params
    foreach ($params as $key => $value) {
        $type = PDO::PARAM_STR;
        if (is_int($value)) $type = PDO::PARAM_INT;
        $stmt->bindValue($key, $value, $type);
    }
    // Limit/Offset must be int
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $leads = $stmt->fetchAll();
    
    // Decode custom_data for each lead
    foreach ($leads as &$lead) {
        if ($lead['custom_data']) {
            $customData = json_decode($lead['custom_data'], true);
            $lead['custom_data'] = is_array($customData) ? $customData : [];
        } else {
            $lead['custom_data'] = [];
        }
    }

    // Get Total Count for Pagination
    // We construct the count query by reusing the exact same conditions as the main query
    // This ensures consistency without duplicating logic
    $count_sql = "SELECT COUNT(*) FROM leads l WHERE " . $filter['where'];
    
    // We need to rebuild the WHERE clause components that were added to standard SQL
    // A better approach is to use the exact same logic structure
    
    // Re-applying all filters to the count query string
    if ($search) $count_sql .= " AND (l.name LIKE :search1 OR l.company LIKE :search2 OR l.email LIKE :search3 OR l.phone LIKE :search4)";
    if ($stage_id) $count_sql .= " AND l.stage_id = :stage_id";
    if ($source) $count_sql .= " AND l.source = :source";
    if ($name) $count_sql .= " AND l.name LIKE :name";
    if ($title) $count_sql .= " AND l.title LIKE :title";
    if ($company) $count_sql .= " AND l.company LIKE :company";
    if ($email) $count_sql .= " AND l.email LIKE :email";
    if ($phone) $count_sql .= " AND l.phone LIKE :phone";
    if ($lead_value_min !== '') $count_sql .= " AND l.lead_value >= :lead_value_min";
    if ($lead_value_max !== '') $count_sql .= " AND l.lead_value <= :lead_value_max";
    if ($created_at_from) $count_sql .= " AND DATE(l.created_at) >= :created_at_from";
    if ($created_at_to) $count_sql .= " AND DATE(l.created_at) <= :created_at_to";
    if ($updated_at_from) $count_sql .= " AND DATE(l.updated_at) >= :updated_at_from";
    if ($updated_at_to) $count_sql .= " AND DATE(l.updated_at) <= :updated_at_to";

    // Custom Fields logic (duplicated from above to ensure count matches)
    // Custom Fields logic (duplicated from above to ensure count matches)
    // Note: $customFilters is already populated above from $_GET
    foreach ($customFilters as $fieldKey => $value) {
        $safeParam = ":cf_" . md5($fieldKey); // Use matching safe param name logic

        if (preg_match('/^(.+)_min$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $count_sql .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) >= {$safeParam}_min";
        } elseif (preg_match('/^(.+)_max$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $count_sql .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) AS DECIMAL) <= {$safeParam}_max";
        } elseif (preg_match('/^(.+)_from$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $count_sql .= " AND DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) >= {$safeParam}_from";
        } elseif (preg_match('/^(.+)_to$/', $fieldKey, $matches)) {
            $fieldName = $matches[1];
            $count_sql .= " AND DATE(JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"'))) <= {$safeParam}_to";
        } else {
            $fieldName = $fieldKey;
            $count_sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldName\"')) LIKE {$safeParam}";
        }
    }

    $count_stmt = $pdo->prepare($count_sql);
    
    // Bind all parameters used in the main query (excluding limit/offset)
    foreach ($params as $key => $value) {
        // Skip limit/offset if they were accidentally added to params (though our code didn't add them there)
        if ($key === ':limit' || $key === ':offset') continue;
        
        $type = PDO::PARAM_STR;
        if (is_int($value)) $type = PDO::PARAM_INT;
        $count_stmt->bindValue($key, $value, $type);
    }

    $count_stmt->execute();
    $total = $count_stmt->fetchColumn();

    echo json_encode([
        "data" => $leads,
        "meta" => [
            "total" => $total,
            "limit" => $limit,
            "offset" => $offset
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch leads: " . $e->getMessage()]);
}
