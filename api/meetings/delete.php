<?php
/**
 * Delete Meeting API
 * DELETE /api/meetings/delete.php?id=123
 */

require_once '../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    
    // Check permission
    if (!$permissionManager->hasFeature('delete_meetings')) {
        ApiResponse::error('Permission denied. You do not have access to delete meetings.', 403);
    }
    
    // Get meeting ID
    $meetingId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $meetingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $meetingId = isset($input['id']) ? (int)$input['id'] : null;
    }
    
    if (!$meetingId) {
        ApiResponse::error('Missing required parameter: id');
    }
    
    // Get meeting details
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
    
    // Delete meeting
    $stmt = $pdo->prepare("DELETE FROM meetings WHERE id = :id");
    $stmt->execute([':id' => $meetingId]);
    
    // Log activity
    $permissionManager->logActivity(
        'meeting_deleted',
        $meeting['lead_id'],
        "Deleted meeting: {$meeting['title']}"
    );
    
    ApiResponse::success(['message' => 'Meeting deleted successfully']);
    
} catch (PDOException $e) {
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
