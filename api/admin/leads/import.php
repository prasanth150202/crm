<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$org_id = (int)($_POST['org_id'] ?? 0);
if (!$org_id) ApiResponse::error('org_id is required', 400);

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    ApiResponse::error('CSV file is required', 400);
}

// Validate org exists
$stmt = $pdo->prepare("SELECT id FROM organizations WHERE id = ?");
$stmt->execute([$org_id]);
if (!$stmt->fetch()) ApiResponse::error('Organization not found', 404);

$file = fopen($_FILES['csv']['tmp_name'], 'r');
if (!$file) ApiResponse::error('Could not read CSV file', 400);

$headers = fgetcsv($file);
if (!$headers) {
    fclose($file);
    ApiResponse::error('CSV file is empty or malformed', 400);
}

$headers = array_map('strtolower', array_map('trim', $headers));

$allowed = ['name','email','phone','company','stage_id','source','lead_value','address','city','state','country'];
$colMap  = [];
foreach ($headers as $i => $h) {
    if (in_array($h, $allowed)) {
        $colMap[$h] = $i;
    }
}

if (!isset($colMap['name'])) {
    fclose($file);
    ApiResponse::error("CSV must have a 'name' column", 400);
}

$inserted = 0;
$errors   = [];

$stmt = $pdo->prepare(
    "INSERT INTO leads (org_id, name, email, phone, company, stage_id, source, lead_value, address, city, state, country)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

while (($row = fgetcsv($file)) !== false) {
    $g = fn($col) => isset($colMap[$col]) && isset($row[$colMap[$col]]) ? trim($row[$colMap[$col]]) : null;
    $name = $g('name');
    if (!$name) continue;
    try {
        $stmt->execute([
            $org_id, $name, $g('email'), $g('phone'), $g('company'),
            $g('stage_id') ?: null, $g('source'), $g('lead_value') ?: null,
            $g('address'), $g('city'), $g('state'), $g('country')
        ]);
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
fclose($file);

ApiResponse::success(['imported' => $inserted, 'errors' => array_slice($errors, 0, 10), 'message' => "$inserted leads imported"]);
