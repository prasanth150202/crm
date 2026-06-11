<?php
/**
 * Create Meeting API
 * POST /api/meetings/create.php
 */

require_once '../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    // Check permission
    if (!$permissionManager->hasFeature('create_meetings')) {
        ApiResponse::error('Permission denied. You do not have access to create meetings.', 403);
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['lead_id', 'title', 'meeting_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            ApiResponse::error("Missing required field: $field");
        }
    }
    
    $leadId = (int)$input['lead_id'];
    $title = trim($input['title']);
    $meetingDate = $input['meeting_date'];
    $duration = isset($input['duration']) ? (int)$input['duration'] : 30;
    $mode = isset($input['mode']) ? $input['mode'] : 'in_person';
    $notes = isset($input['description']) ? trim($input['description']) : null;
    $outcome = isset($input['outcome']) ? trim($input['outcome']) : null;
    
    // Validate mode
    $validModes = ['in_person', 'phone', 'video', 'other'];
    if (!in_array($mode, $validModes)) {
        ApiResponse::error('Invalid meeting mode. Must be one of: ' . implode(', ', $validModes));
    }
    
    // Verify lead exists and user has access
    $stmt = $pdo->prepare("
        SELECT id, org_id FROM leads 
        WHERE id = :lead_id AND org_id = :org_id
    ");
    $stmt->execute([
        ':lead_id' => $leadId,
        ':org_id' => $user['org_id']
    ]);
    
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) {
        ApiResponse::error('Lead not found or access denied');
    }
    
    // Validate date format
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $meetingDate);
    if (!$dateTime) {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i', $meetingDate);
        if ($dateTime) {
            $meetingDate = $dateTime->format('Y-m-d H:i:s');
        } else {
            ApiResponse::error('Invalid date format. Use Y-m-d H:i:s or Y-m-d H:i');
        }
    }
    
    // Insert meeting
    $stmt = $pdo->prepare("
        INSERT INTO meetings 
        (org_id, lead_id, created_by, title, meeting_date, duration, mode, notes, outcome)
        VALUES 
        (:org_id, :lead_id, :created_by, :title, :meeting_date, :duration, :mode, :notes, :outcome)
    ");
    
    $stmt->execute([
        ':org_id' => $user['org_id'],
        ':lead_id' => $leadId,
        ':created_by' => $user['id'],
        ':title' => $title,
        ':meeting_date' => $meetingDate,
        ':duration' => $duration,
        ':mode' => $mode,
        ':notes' => $notes,
        ':outcome' => $outcome
    ]);
    
    $meetingId = $pdo->lastInsertId();
    
    // Log activity
    $permissionManager->logActivity(
        'meeting_created',
        $leadId,
        "Created meeting: $title"
    );
    
    ApiResponse::success([
        'meeting_id' => $meetingId,
        'message' => 'Meeting created successfully'
    ]);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
