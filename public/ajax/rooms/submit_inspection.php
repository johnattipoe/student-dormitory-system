<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\RoomService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require house-master or admin role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$roomId = sanitize($input['roomId'] ?? '');
$condition = sanitize($input['condition'] ?? '');
$issues = sanitize($input['issues'] ?? '');
$recommendations = sanitize($input['recommendations'] ?? '');

// Validate required fields
if (empty($roomId) || empty($condition) || !in_array($condition, ['good', 'fair', 'poor'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid roomId or condition']);
    exit;
}

try {
    $roomService = new RoomService();
    
    // Create inspection record
    $inspectionData = [
        'roomId' => $roomId,
        'condition' => $condition,
        'issues' => $issues,
        'recommendations' => $recommendations,
        'inspectedBy' => current_user_id(),
        'inspectedByName' => current_user()['name'] ?? 'System',
        'inspectionDate' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d')
    ];
    
    // Update room with inspection data
    $result = $roomService->update($roomId, array_merge(['condition' => $condition, 'lastInspected' => date('Y-m-d')], $inspectionData));
    
    if ($result) {
        // Log activity
        ActivityLogService::log(
            current_user_id() ?? 'unknown',
            'room_inspection_submitted',
            'Room ' . $roomId . ' inspection - Condition: ' . $condition,
            ['roomId' => $roomId, 'condition' => $condition, 'hasIssues' => !empty($issues)]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Inspection submitted']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to submit inspection']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
