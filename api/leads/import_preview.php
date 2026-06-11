<?php
// api/leads/import_preview.php
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded or upload error"]);
    exit;
}

$file = $_FILES['file'];
$org_id = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;

if (!$org_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}

// Validate file type
$allowedTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes) && !str_ends_with($file['name'], '.csv')) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid file type. Only CSV files are allowed."]);
    exit;
}

try {
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception("Could not open file");
    }

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception("Empty file or invalid CSV format");
    }

    // Clean headers
    $headers = array_map('trim', $headers);

    // Read preview rows (first 10)
    $previewRows = [];
    $rowCount = 0;
    while (($row = fgetcsv($handle)) !== false && $rowCount < 10) {
        $previewRows[] = $row;
        $rowCount++;
    }

    // Count total rows
    $totalRows = $rowCount;
    while (fgetcsv($handle) !== false) {
        $totalRows++;
    }

    fclose($handle);

    // Detect duplicates by email
    $emails = [];
    $handle = fopen($file['tmp_name'], 'r');
    fgetcsv($handle); // Skip header
    
    // Find email column index
    $emailColIndex = array_search('email', array_map('strtolower', $headers));
    if ($emailColIndex === false) {
        $emailColIndex = array_search('Email', $headers);
    }

    $duplicateCount = 0;
    if ($emailColIndex !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[$emailColIndex]) && !empty($row[$emailColIndex])) {
                $email = trim($row[$emailColIndex]);
                if ($email) {
                    $emails[] = $email;
                }
            }
        }
        fclose($handle);

        // Check against database
        if (!empty($emails)) {
            $placeholders = str_repeat('?,', count($emails) - 1) . '?';
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE org_id = ? AND email IN ($placeholders)");
            $stmt->execute(array_merge([$org_id], $emails));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $duplicateCount = $result['count'];
        }
    }

    // Get existing custom fields from lead data
    $customFields = [];
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT custom_data FROM leads WHERE org_id = ? AND custom_data IS NOT NULL AND custom_data != ''");
        $stmt->execute([$org_id]);
        $allFields = [];
        
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['custom_data'], true);
            if (is_array($data)) {
                foreach (array_keys($data) as $fieldName) {
                    if (!in_array($fieldName, $allFields)) {
                        $allFields[] = $fieldName;
                        $customFields[] = [
                            'field_name' => $fieldName,
                            'field_type' => 'text',
                            'field_options' => ''
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        $customFields = [];
    }

    echo json_encode([
        "success" => true,
        "headers" => $headers,
        "preview" => $previewRows,
        "totalRows" => $totalRows,
        "duplicateCount" => $duplicateCount,
        "customFields" => $customFields
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to process file: " . $e->getMessage()]);
}
