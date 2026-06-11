<?php
/**
 * Update Meeting API
 * PUT /api/meetings/update.php
 */

require_once '../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        ApiResponse::error('Missing required field: id');
    }
    
    $meetingId = (int)$input['id'];
    
    // Get existing meeting
    $stmt = $pdo->prepare("
        SELECT * FROM meetings 
        WHERE id = :id AND org_id = :org_id
    ");
    $stmt->execute([
        ':id' => $meetingId,
        ':org_id' => $user['org_id']
    ]);
    
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        ApiResponse::error('Meeting not found');
    }
    
    // Check permissions
    $canEdit = false;
    if ($permissionManager->hasFeature('edit_all_meetings')) {
        $canEdit = true;
    } elseif ($permissionManager->hasFeature('edit_own_meetings') && $meeting['created_by'] == $user['id']) {
        $canEdit = true;
    }
    
    if (!$canEdit) {
        ApiResponse::error('Permission denied. You cannot edit this meeting.', 403);
    }
    
    // Build update query
    $updates = [];
    $params = [':id' => $meetingId];
    
    if (isset($input['title'])) {
        $updates[] = "title = :title";
        $params[':title'] = trim($input['title']);
    }
    
    if (isset($input['meeting_date'])) {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input['meeting_date']);
        if (!$dateTime) {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i', $input['meeting_date']);
            if ($dateTime) {
                $input['meeting_date'] = $dateTime->format('Y-m-d H:i:s');
            } else {
                ApiResponse::error('Invalid date format');
            }
        }
        $updates[] = "meeting_date = :meeting_date";
        $params[':meeting_date'] = $input['meeting_date'];
    }
    
    if (isset($input['duration'])) {
        $updates[] = "duration = :duration";
        $params[':duration'] = (int)$input['duration'];
    }
    
    if (isset($input['mode'])) {
        $validModes = ['in_person', 'phone', 'video', 'other'];
        if (!in_array($input['mode'], $validModes)) {
            ApiResponse::error('Invalid meeting mode');
        }
        $updates[] = "mode = :mode";
        $params[':mode'] = $input['mode'];
    }
    
    if (isset($input['description'])) {
        $updates[] = "notes = :notes";
        $params[':notes'] = trim($input['description']);
    }
    
    if (isset($input['outcome'])) {
        $updates[] = "outcome = :outcome";
        $params[':outcome'] = trim($input['outcome']);
    }
    
    if (empty($updates)) {
        ApiResponse::error('No fields to update');
    }
    
    // Update meeting
    $sql = "UPDATE meetings SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Log activity
    $permissionManager->logActivity(
        'meeting_updated',
        $meeting['lead_id'],
        "Updated meeting: {$meeting['title']}"
    );
    
    ApiResponse::success(['message' => 'Meeting updated successfully']);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
