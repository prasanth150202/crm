<?php
// api/reports/chart_data.php - Flexible data endpoint for custom charts
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$org_id = $_SESSION['org_id'];
if (!$org_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}
$field = $_GET['field'] ?? 'stage_id';
$aggregation = $_GET['aggregation'] ?? 'count';
$chart_id = $_GET['chart_id'] ?? null;
$conditionSql = "";
$conditionParams = [];

// If chart_id is provided, get chart config
$chart_type_override = null;
if ($chart_id) {
    $stmt = $pdo->prepare("SELECT * FROM custom_charts WHERE id = ? AND org_id = ?");
    $stmt->execute([$chart_id, $org_id]);
    $chart = $stmt->fetch();

    if ($chart) {
        $field = $chart['x_axis'] ?? 'stage_id';
        $aggregation = $chart['aggregation'] ?? 'count';
        $chart_type_override = $chart['chart_type'] ?? null;

        // Build condition SQL from saved conditions JSON
        $conditionSql = "";
        $conditionParams = [];
        $rawConditions = !empty($chart['conditions']) ? json_decode($chart['conditions'], true) : [];
        if (is_array($rawConditions)) {
            $allowedFields = ['stage_id','source','company','owner_email','lead_name','title','email','phone'];
            foreach ($rawConditions as $i => $cond) {
                $cf = preg_replace('/[^a-zA-Z0-9_]/', '', $cond['field'] ?? '');
                $op = $cond['operator'] ?? '=';
                $cv = $cond['value'] ?? '';
                $pkey = ":cond_{$i}";

                // Special virtual field: has_meeting
                if ($cf === 'has_meeting') {
                    if ($cv === 'yes') {
                        $conditionSql .= " AND EXISTS (SELECT 1 FROM meetings m WHERE m.lead_id = leads.id)";
                    } else {
                        $conditionSql .= " AND NOT EXISTS (SELECT 1 FROM meetings m WHERE m.lead_id = leads.id)";
                    }
                    continue;
                }

                if (!in_array($cf, $allowedFields)) continue;
                if ($op === '=' )           { $conditionSql .= " AND `$cf` = $pkey";         $conditionParams[$pkey] = $cv; }
                elseif ($op === '!=' )      { $conditionSql .= " AND `$cf` != $pkey";        $conditionParams[$pkey] = $cv; }
                elseif ($op === 'contains') { $conditionSql .= " AND `$cf` LIKE $pkey";      $conditionParams[$pkey] = '%'.$cv.'%'; }
                elseif ($op === 'not_contains'){ $conditionSql .= " AND `$cf` NOT LIKE $pkey"; $conditionParams[$pkey] = '%'.$cv.'%'; }
                elseif ($op === 'is_empty' ){ $conditionSql .= " AND (`$cf` IS NULL OR `$cf` = '')"; }
                elseif ($op === 'not_empty'){ $conditionSql .= " AND (`$cf` IS NOT NULL AND `$cf` != '')"; }
            }
        }
    }
}

// ── Scorecard: stage_count — count leads in a specific stage ─────────────────
// data_field = 'stage_count', x_axis = stage id (e.g. 'proposal')
if ($chart_type_override === 'scorecard' && isset($chart) && $chart['data_field'] === 'stage_count') {
    $range = $_GET['range'] ?? 'all';
    $dateCondition = "";
    $dateParams = [':org_id' => $org_id];
    if ($range === 'today') {
        $dateCondition = " AND DATE(created_at) = CURDATE()";
    } elseif ($range === '7' || $range === 'last_7_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($range === '30' || $range === 'last_30_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($range === 'custom' && isset($_GET['start'], $_GET['end'])) {
        $dateCondition = " AND created_at >= :start_date AND created_at <= :end_date";
        $dateParams[':start_date'] = $_GET['start'] . ' 00:00:00';
        $dateParams[':end_date']   = $_GET['end']   . ' 23:59:59';
    }
    $stage = $chart['x_axis'] ?? 'proposal';
    $dateParams[':stage'] = $stage;
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(lead_value),0) as total_value FROM leads WHERE org_id=:org_id AND stage_id=:stage $dateCondition $conditionSql");
    $stmt->execute(array_merge($dateParams, $conditionParams));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $val = ($chart['aggregation'] === 'sum') ? (float)$row['total_value'] : (int)$row['cnt'];
    echo json_encode([
        "labels"   => [ucfirst($stage)],
        "datasets" => [["data" => [$val], "backgroundColor" => ["#8B5CF6"], "borderColor" => ["#8B5CF6"]]],
        "comboCounts" => [(int)$row['cnt']],
        "comboValues" => [(float)$row['total_value']],
        "scorecard_meta" => ["stage" => $stage, "count" => (int)$row['cnt'], "value" => (float)$row['total_value']]
    ]);
    exit;
}

// ── Scorecard: meetings — total meetings booked ───────────────────────────────
// data_field = 'meetings'
if ($chart_type_override === 'scorecard' && isset($chart) && $chart['data_field'] === 'meetings') {
    $range = $_GET['range'] ?? 'all';
    $dateCondition = "";
    $dateParams = [':org_id' => $org_id];
    if ($range === 'today') {
        $dateCondition = " AND DATE(m.created_at) = CURDATE()";
    } elseif ($range === '7' || $range === 'last_7_days') {
        $dateCondition = " AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($range === '30' || $range === 'last_30_days') {
        $dateCondition = " AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($range === 'custom' && isset($_GET['start'], $_GET['end'])) {
        $dateCondition = " AND m.created_at >= :start_date AND m.created_at <= :end_date";
        $dateParams[':start_date'] = $_GET['start'] . ' 00:00:00';
        $dateParams[':end_date']   = $_GET['end']   . ' 23:59:59';
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM meetings m WHERE m.org_id=:org_id $dateCondition");
    $stmt->execute($dateParams);
    $cnt = (int)$stmt->fetchColumn();
    echo json_encode([
        "labels"   => ["Meetings"],
        "datasets" => [["data" => [$cnt], "backgroundColor" => ["#0891B2"], "borderColor" => ["#0891B2"]]],
        "comboCounts" => [$cnt],
        "comboValues" => [0],
        "scorecard_meta" => ["count" => $cnt]
    ]);
    exit;
}

// channel_stage_matrix: special response — source × stage counts
if ($chart_type_override === 'channel_stage_matrix') {
    header("Content-Type: application/json");
    $range = $_GET['range'] ?? 'all';
    $dateCondition = "";
    $dateParams = [];
    if ($range === 'today') {
        $dateCondition = " AND DATE(created_at) = CURDATE()";
    } elseif ($range === '7' || $range === 'last_7_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($range === '30' || $range === 'last_30_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($range === 'custom' && isset($_GET['start'], $_GET['end'])) {
        $dateCondition = " AND created_at >= :start_date AND created_at <= :end_date";
        $dateParams[':start_date'] = $_GET['start'] . ' 00:00:00';
        $dateParams[':end_date']   = $_GET['end']   . ' 23:59:59';
    }
    $stages = ['new','contacted','qualified','proposal','won','lost'];
    $stmtM = $pdo->prepare("
        SELECT COALESCE(source,'Unknown') as source, stage_id, COUNT(*) as cnt,
               COALESCE(SUM(lead_value),0) as total_value
        FROM leads
        WHERE org_id = :org_id $dateCondition
        GROUP BY source, stage_id
        ORDER BY source
    ");
    $stmtM->execute(array_merge([':org_id' => $org_id], $dateParams));
    $rows = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Meetings per source (join leads → meetings)
    $meetDateCond = str_replace('created_at', 'l.created_at', $dateCondition);
    $stmtMeet = $pdo->prepare("
        SELECT COALESCE(l.source,'Unknown') as source, COUNT(m.id) as meeting_cnt
        FROM meetings m
        JOIN leads l ON m.lead_id = l.id
        WHERE m.org_id = :org_id $meetDateCond
        GROUP BY l.source
    ");
    $stmtMeet->execute(array_merge([':org_id' => $org_id], $dateParams));
    $meetingsBySource = [];
    foreach ($stmtMeet->fetchAll(PDO::FETCH_ASSOC) as $mr) {
        $meetingsBySource[$mr['source']] = (int)$mr['meeting_cnt'];
    }

    // Build matrix: [source => [stage => count]]
    $matrix = [];
    $sourceTotals = [];
    foreach ($rows as $r) {
        $src = $r['source'];
        $stg = $r['stage_id'];
        if (!isset($matrix[$src])) {
            $matrix[$src] = ['total_value' => 0, 'meetings' => 0];
            foreach ($stages as $s) $matrix[$src][$s] = 0;
        }
        $matrix[$src][$stg] = (int)$r['cnt'];
        $matrix[$src]['total_value'] += (float)$r['total_value'];
        $sourceTotals[$src] = ($sourceTotals[$src] ?? 0) + (int)$r['cnt'];
    }
    // Attach meeting counts
    foreach ($meetingsBySource as $src => $cnt) {
        if (!isset($matrix[$src])) {
            $matrix[$src] = ['total_value' => 0, 'meetings' => 0];
            foreach ($stages as $s) $matrix[$src][$s] = 0;
        }
        $matrix[$src]['meetings'] = $cnt;
    }
    arsort($sourceTotals);

    echo json_encode([
        'type'    => 'channel_stage_matrix',
        'stages'  => $stages,
        'matrix'  => $matrix,
        'order'   => array_keys($sourceTotals),
    ]);
    exit;
}

try {
    // Date Filtering Logic
    $range = $_GET['range'] ?? 'last_7_days';
    $start_date = $_GET['start'] ?? null;
    $end_date = $_GET['end'] ?? null;
    $dateCondition = "";
    $dateParams = [];

    if ($range === 'all') {
        $dateCondition = "";
    } else if ($range === 'today') {
        $dateCondition = " AND DATE(created_at) = CURDATE()";
    } else if ($range === '7' || $range === 'last_7_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } else if ($range === '30' || $range === 'last_30_days') {
        $dateCondition = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } else if ($range === 'custom' && $start_date && $end_date) {
        $dateCondition = " AND created_at >= :start_date AND created_at <= :end_date";
        $dateParams[':start_date'] = $start_date . ' 00:00:00';
        $dateParams[':end_date'] = $end_date . ' 23:59:59';
    }

    // Build dynamic query based on parameters
    $extraWhere = $dateCondition . $conditionSql;
    if ($field === 'created_week') {
        $query = "SELECT DATE_FORMAT(created_at, '%Y-W%u') as label, COUNT(*) as count, COALESCE(SUM(lead_value),0) as total_value
                  FROM leads WHERE org_id = :org_id $extraWhere
                  GROUP BY label ORDER BY label ASC";
    } else if ($field === 'stage_id') {
        $query = "SELECT stage_id as label, COUNT(*) as count, COALESCE(SUM(lead_value),0) as total_value
                  FROM leads
                  WHERE org_id = :org_id $extraWhere
                  GROUP BY stage_id
                  ORDER BY FIELD(stage_id, 'new', 'contacted', 'qualified', 'proposal', 'won', 'lost')";
    } else if ($field === 'source') {
        $query = "SELECT COALESCE(source, 'Unknown') as label, COUNT(*) as count, COALESCE(SUM(lead_value),0) as total_value
                  FROM leads
                  WHERE org_id = :org_id $extraWhere
                  GROUP BY source ORDER BY count DESC";
    } else if (strpos($field, 'custom_') === 0) {
        $customFieldName = substr($field, 7);
        $customFieldName = preg_replace('/[^a-zA-Z0-9_ ]/', '', $customFieldName);
        $query = "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(custom_data, :json_path)), 'Unknown') as label, COUNT(*) as count, 0 as total_value
                  FROM leads
                  WHERE org_id = :org_id $extraWhere
                  GROUP BY label ORDER BY count DESC";
    } else {
        $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        $query = "SELECT `$field` as label, COUNT(*) as count, 0 as total_value
                  FROM leads
                  WHERE org_id = :org_id $extraWhere
                  GROUP BY `$field` ORDER BY count DESC";
    }

    $stmt = $pdo->prepare($query);
    $executeParams = array_merge([':org_id' => $org_id], $dateParams, $conditionParams);
    if (strpos($field, 'custom_') === 0) {
        $customFieldName = substr($field, 7);
        $customFieldName = preg_replace('/[^a-zA-Z0-9_ ]/', '', $customFieldName);
        $executeParams[':json_path'] = '$."' . $customFieldName . '"';
    }
    $stmt->execute($executeParams);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for Chart.js
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $label = $row['label'];
        if ($field === 'stage_id') {
            // Convert stage_id to readable names
            $stageNames = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'won' => 'Won', 'lost' => 'Lost'];
            $label = $stageNames[$label] ?? ucfirst($label);
        }
        $labels[] = htmlspecialchars($label ?? 'Unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        
        if ($aggregation === 'sum' && isset($row['total_value'])) {
            $values[] = (float)$row['total_value'];
        } else if ($aggregation === 'avg' && isset($row['total_value']) && $row['count'] > 0) {
            $values[] = (float)$row['total_value'] / (float)$row['count'];
        } else {
            $values[] = (float)$row['count'];
        }
    }
    
    echo json_encode([
        "labels" => $labels,
        "datasets" => [[
            "data" => $values,
            "backgroundColor" => ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
            "borderColor" => ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
        ]],
        "comboCounts" => array_column($results, 'count'),
        "comboValues" => array_column($results, 'total_value')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
