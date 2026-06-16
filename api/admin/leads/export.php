<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$org_id = (int)($_GET['org_id'] ?? 0);

$params = [];
$where  = '';
if ($org_id) {
    $where    = 'WHERE l.org_id = ?';
    $params[] = $org_id;
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.name, l.email, l.phone, l.company, l.stage_id, l.source,
            l.lead_value, l.created_at, o.name AS org_name, u.full_name AS assigned_to
     FROM leads l
     JOIN organizations o ON o.id = l.org_id
     LEFT JOIN users u ON u.id = l.assigned_to
     $where
     ORDER BY l.created_at DESC"
);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$filename = 'leads_export_' . ($org_id ?: 'all') . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($leads) {
    fputcsv($out, array_keys($leads[0]));
    foreach ($leads as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['id','name','email','phone','company','stage_id','source','lead_value','created_at','org_name','assigned_to']);
}
fclose($out);
exit;
