<?php
// api/leads/facets.php
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

$org_id = (int)($currentUser['org_id'] ?? $_GET['org_id']);

function normArray($raw): array {
    if ($raw === null || $raw === '') return [];
    if (is_array($raw)) return array_values(array_filter(array_map('trim', $raw)));
    return array_values(array_filter([trim((string)$raw)]));
}

try {
    $pm = getPermissionManager($pdo, $currentUser);
    $accessFilter = $pm->getLeadsFilter('l');

    $search       = isset($_GET['search']) ? trim($_GET['search']) : '';
    $stageVals    = normArray($_GET['stage_id']    ?? null);
    $sourceVals   = normArray($_GET['source']      ?? null);
    $assignedVals = normArray($_GET['assigned_to'] ?? null);

    $customFacetFilters = [];
    if (isset($_GET['facet_custom']) && is_array($_GET['facet_custom'])) {
        foreach ($_GET['facet_custom'] as $k => $v) {
            $arr = normArray($v);
            if (!empty($arr)) $customFacetFilters[$k] = $arr;
        }
    }

    /**
     * Build WHERE + params for filtered queries, excluding one facet field.
     */
    $buildWhere = function(?string $excludeField) use (
        $accessFilter, $search, $stageVals, $sourceVals, $assignedVals, $customFacetFilters
    ): array {
        $where  = [$accessFilter['where']];
        $params = $accessFilter['params'];

        if ($search) {
            $where[] = "(l.name LIKE :s1 OR l.company LIKE :s2 OR l.email LIKE :s3 OR l.first_name LIKE :s4 OR l.last_name LIKE :s5 OR l.title LIKE :s6)";
            $params += [':s1'=>"%$search%",':s2'=>"%$search%",':s3'=>"%$search%",':s4'=>"%$search%",':s5'=>"%$search%",':s6'=>"%$search%"];
        }

        if (!empty($stageVals) && $excludeField !== 'stage_id') {
            $ph = [];
            foreach ($stageVals as $i => $v) { $ph[] = ":fst_$i"; $params[":fst_$i"] = $v; }
            $where[] = "l.stage_id IN (" . implode(',', $ph) . ")";
        }

        if (!empty($sourceVals) && $excludeField !== 'source') {
            $ph = [];
            foreach ($sourceVals as $i => $v) { $ph[] = ":fsr_$i"; $params[":fsr_$i"] = $v; }
            $where[] = "l.source IN (" . implode(',', $ph) . ")";
        }

        if (!empty($assignedVals) && $excludeField !== 'assigned_to') {
            $nullCond = in_array('unassigned', $assignedVals);
            $intVals  = array_filter($assignedVals, fn($v) => $v !== 'unassigned' && is_numeric($v));
            $conds = [];
            if ($nullCond) $conds[] = "l.assigned_to IS NULL";
            if (!empty($intVals)) {
                $ph = [];
                foreach (array_values($intVals) as $i => $v) { $ph[] = ":fas_$i"; $params[":fas_$i"] = (int)$v; }
                $conds[] = "l.assigned_to IN (" . implode(',', $ph) . ")";
            }
            if (!empty($conds)) $where[] = "(" . implode(' OR ', $conds) . ")";
        }

        foreach ($customFacetFilters as $fieldKey => $vals) {
            if ($excludeField === "custom_{$fieldKey}") continue;
            $jsonRef = "JSON_UNQUOTE(JSON_EXTRACT(l.custom_data, '$.\"$fieldKey\"'))";
            $ph = [];
            foreach ($vals as $i => $v) { $ph[] = ":cff_{$i}_" . md5($fieldKey); $params[":cff_{$i}_" . md5($fieldKey)] = $v; }
            $where[] = "{$jsonRef} IN (" . implode(',', $ph) . ")";
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    };

    $runQuery = function(string $sql, array $params) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    // Helper: build a count map from query results
    $countMap = function(array $rows, string $valueCol = 'value'): array {
        $map = [];
        foreach ($rows as $r) {
            $key = $r[$valueCol] === null ? '__null__' : (string)$r[$valueCol];
            $map[$key] = (int)$r['count'];
        }
        return $map;
    };

    // ── Stage facets ──────────────────────────────────────────────────────────
    // Always include all known stages + any org-specific ones, with count=0 if none match
    $knownStages  = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
    $stageLabels  = ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','proposal'=>'Proposal','won'=>'Won','lost'=>'Lost'];

    // All stages that exist for this org (so custom stage values also appear)
    $orgStagesStmt = $pdo->prepare("SELECT DISTINCT stage_id FROM leads WHERE org_id=:org AND stage_id IS NOT NULL AND stage_id!=''");
    $orgStagesStmt->execute([':org' => $org_id]);
    $orgStages = $orgStagesStmt->fetchAll(PDO::FETCH_COLUMN);
    $allStages = array_unique(array_merge($knownStages, $orgStages));
    usort($allStages, function($a, $b) use ($knownStages) {
        $ai = array_search($a, $knownStages);
        $bi = array_search($b, $knownStages);
        if ($ai !== false && $bi !== false) return $ai - $bi;
        if ($ai !== false) return -1;
        if ($bi !== false) return 1;
        return strcmp($a, $b);
    });

    // Filtered counts (excluding stage filter)
    $b = $buildWhere('stage_id');
    $filteredStageRows = $runQuery(
        "SELECT l.stage_id AS value, COUNT(*) AS count FROM leads l WHERE {$b['where']} AND l.stage_id IS NOT NULL AND l.stage_id!='' GROUP BY l.stage_id",
        $b['params']
    );
    $stageCounts = $countMap($filteredStageRows);

    $stageFacets = array_map(fn($v) => [
        'value' => $v,
        'label' => $stageLabels[$v] ?? ucfirst($v),
        'count' => $stageCounts[$v] ?? 0,
    ], $allStages);

    // ── Source facets ─────────────────────────────────────────────────────────
    $orgSourcesStmt = $pdo->prepare("SELECT DISTINCT source FROM leads WHERE org_id=:org AND source IS NOT NULL AND source!='' ORDER BY source");
    $orgSourcesStmt->execute([':org' => $org_id]);
    $allSources = $orgSourcesStmt->fetchAll(PDO::FETCH_COLUMN);

    $b = $buildWhere('source');
    $filteredSourceRows = $runQuery(
        "SELECT l.source AS value, COUNT(*) AS count FROM leads l WHERE {$b['where']} AND l.source IS NOT NULL AND l.source!='' GROUP BY l.source",
        $b['params']
    );
    $sourceCounts = $countMap($filteredSourceRows);

    $sourceFacets = array_map(fn($s) => [
        'value' => $s, 'label' => $s, 'count' => $sourceCounts[$s] ?? 0,
    ], $allSources);
    usort($sourceFacets, fn($a, $b) => $b['count'] - $a['count'] ?: strcmp($a['value'], $b['value']));

    // ── Assigned-to facets ────────────────────────────────────────────────────
    $allAssignedStmt = $pdo->prepare(
        "SELECT l.assigned_to AS value, COALESCE(u.full_name,'Unassigned') AS label
         FROM leads l LEFT JOIN users u ON l.assigned_to = u.id
         WHERE l.org_id=:org GROUP BY l.assigned_to, u.full_name ORDER BY COUNT(*) DESC"
    );
    $allAssignedStmt->execute([':org' => $org_id]);
    $allAssigned = $allAssignedStmt->fetchAll(PDO::FETCH_ASSOC);

    $b = $buildWhere('assigned_to');
    $filteredAssignedRows = $runQuery(
        "SELECT l.assigned_to AS value, COUNT(*) AS count FROM leads l WHERE {$b['where']} GROUP BY l.assigned_to",
        $b['params']
    );
    // Build count map with null handling
    $assignedCounts = [];
    foreach ($filteredAssignedRows as $r) {
        $key = $r['value'] === null ? '__null__' : (string)$r['value'];
        $assignedCounts[$key] = (int)$r['count'];
    }

    $assignedFacets = array_map(fn($r) => [
        'value' => $r['value'] === null ? 'unassigned' : (string)$r['value'],
        'label' => $r['label'],
        'count' => $r['value'] === null
            ? ($assignedCounts['__null__'] ?? 0)
            : ($assignedCounts[(string)$r['value']] ?? 0),
    ], $allAssigned);
    usort($assignedFacets, fn($a, $b) => $b['count'] - $a['count']);

    // ── Custom select-field facets ────────────────────────────────────────────
    $customFacets = [];
    $cfStmt = $pdo->prepare(
        "SELECT field_name, field_options FROM custom_fields WHERE org_id=:org AND field_type='select' AND is_active=1 ORDER BY field_name"
    );
    $cfStmt->execute([':org' => $org_id]);

    foreach ($cfStmt->fetchAll(PDO::FETCH_ASSOC) as $cf) {
        $fieldName  = $cf['field_name'];
        $rawOptions = json_decode($cf['field_options'] ?? '[]', true);
        $allOptions = is_array($rawOptions) ? array_values(array_filter(array_map('trim', $rawOptions))) : [];

        $b = $buildWhere("custom_{$fieldName}");
        $cfRows = $runQuery(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(l.custom_data,'$.\"$fieldName\"')) AS value, COUNT(*) AS count
             FROM leads l WHERE {$b['where']}
               AND JSON_EXTRACT(l.custom_data,'$.\"$fieldName\"') IS NOT NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(l.custom_data,'$.\"$fieldName\"')) NOT IN ('','null')
             GROUP BY value",
            $b['params']
        );
        $cfCounts = $countMap($cfRows);

        // Merge defined options (with 0 count if needed) + any extra values found in data
        $seen = [];
        $facetItems = [];
        foreach ($allOptions as $opt) {
            $facetItems[] = ['value' => $opt, 'count' => $cfCounts[$opt] ?? 0];
            $seen[$opt] = true;
        }
        foreach ($cfRows as $r) {
            if (!isset($seen[$r['value']])) {
                $facetItems[] = ['value' => $r['value'], 'count' => (int)$r['count']];
            }
        }
        usort($facetItems, fn($a, $b) => $b['count'] - $a['count'] ?: strcmp($a['value'], $b['value']));

        if (!empty($facetItems)) {
            $customFacets[$fieldName] = $facetItems;
        }
    }

    echo json_encode([
        'stage_id'    => $stageFacets,
        'source'      => $sourceFacets,
        'assigned_to' => $assignedFacets,
        'custom'      => $customFacets,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load facets: ' . $e->getMessage()]);
}
