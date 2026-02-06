<?php
// api/leads/import.php
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

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded"]);
    exit;
}

$file = $_FILES['file'];
$org_id = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$import_mode = isset($_POST['import_mode']) ? $_POST['import_mode'] : 'skip';
$column_mapping = isset($_POST['column_mapping']) ? json_decode($_POST['column_mapping'], true) : [];

if (!$org_id || !$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

if (!in_array($import_mode, ['skip', 'update', 'overwrite'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid import mode"]);
    exit;
}

try {
    // Simple import without job tracking
    $job_id = time(); // Use timestamp as job ID

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception("Could not open file");
    }

    $headers = fgetcsv($handle);
    $headers = array_map('trim', $headers);

    $success_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    $total_rows = 0;
    $errors = [];

    $pdo->beginTransaction();

    while (($row = fgetcsv($handle)) !== false) {
        $total_rows++;
        
        try {
            // Map columns to lead fields
            $leadData = [];
            $customData = [];
            
            foreach ($column_mapping as $csvCol => $crmField) {
                $colIndex = array_search($csvCol, $headers);
                if ($colIndex !== false && isset($row[$colIndex])) {
                    $value = trim($row[$colIndex]);
                    
                    // Check if it's a custom field (starts with 'custom_')
                    if (strpos($crmField, 'custom_') === 0) {
                        $fieldName = substr($crmField, 7); // Remove 'custom_' prefix
                        $customData[$fieldName] = $value;
                    } else {
                        $leadData[$crmField] = $value;
                    }
                }
            }

            // Validate required fields
            if (empty($leadData['name'])) {
                throw new Exception("Name is required");
            }

            // Check for duplicate by email
            $duplicate = null;
            if (!empty($leadData['email'])) {
                $stmt = $pdo->prepare("SELECT id FROM leads WHERE org_id = ? AND email = ?");
                $stmt->execute([$org_id, $leadData['email']]);
                $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($duplicate) {
                // Handle duplicate based on mode
                if ($import_mode === 'skip') {
                    $skipped_count++;
                    continue;
                } elseif ($import_mode === 'update' || $import_mode === 'overwrite') {
                    // Update existing lead
                    $updateFields = [];
                    $updateValues = [];
                    
                    foreach ($leadData as $field => $value) {
                        if ($import_mode === 'overwrite' || !empty($value)) {
                            $updateFields[] = "$field = ?";
                            $updateValues[] = $value;
                        }
                    }
                    
                    // Handle custom data updates
                    if (!empty($customData)) {
                        if ($import_mode === 'overwrite') {
                            // Replace all custom data
                            $updateFields[] = "custom_data = ?";
                            $updateValues[] = json_encode($customData);
                        } else {
                            // Merge with existing custom data
                            $stmt = $pdo->prepare("SELECT custom_data FROM leads WHERE org_id = ? AND id = ?");
                            $stmt->execute([$org_id, $duplicate['id']]);
                            $existing = $stmt->fetch();
                            
                            $existingCustom = json_decode($existing['custom_data'] ?? '{}', true) ?: [];
                            $mergedCustom = array_merge($existingCustom, $customData);
                            
                            $updateFields[] = "custom_data = ?";
                            $updateValues[] = json_encode($mergedCustom);
                        }
                    }
                    
                    $updateValues[] = $org_id;
                    $updateValues[] = $duplicate['id'];
                    
                    $sql = "UPDATE leads SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE org_id = ? AND id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($updateValues);
                    
                    $updated_count++;
                }
            } else {
                // Insert new lead
                $fields = ['org_id', 'name'];
                $values = [$org_id, $leadData['name']];
                $placeholders = ['?', '?'];

                foreach (['title', 'email', 'phone', 'company', 'lead_value', 'source', 'stage_id'] as $field) {
                    if (isset($leadData[$field]) && $leadData[$field] !== '') {
                        $fields[] = $field;
                        $values[] = $leadData[$field];
                        $placeholders[] = '?';
                    }
                }
                
                // Add custom data if present
                if (!empty($customData)) {
                    $fields[] = 'custom_data';
                    $values[] = json_encode($customData);
                    $placeholders[] = '?';
                }

                $fields[] = 'created_at';
                $fields[] = 'updated_at';
                $placeholders[] = 'NOW()';
                $placeholders[] = 'NOW()';

                $sql = "INSERT INTO leads (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                $success_count++;
            }

        } catch (Exception $e) {
            $error_count++;
            $errors[] = "Row $total_rows: " . $e->getMessage();
        }
    }

    fclose($handle);
    $pdo->commit();

    // Import completed successfully

    echo json_encode([
        "success" => true,
        "job_id" => $job_id,
        "total_rows" => $total_rows,
        "success_count" => $success_count,
        "updated_count" => $updated_count,
        "skipped_count" => $skipped_count,
        "error_count" => $error_count,
        "errors" => array_slice($errors, 0, 10) // Return first 10 errors
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Error occurred during import
    
    http_response_code(500);
    echo json_encode(["error" => "Import failed: " . $e->getMessage()]);
}
