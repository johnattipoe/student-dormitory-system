<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\RoomService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require house-master, houseparent, or admin role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$roomId = sanitize($input['roomId'] ?? '');
$status = sanitize($input['status'] ?? '');

// Validate required fields
if (empty($roomId) || empty($status) || !in_array($status, ['available', 'occupied', 'maintenance'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid roomId or status']);
    exit;
}

try {
    $roomService = new RoomService();
    
    // Update room status
    $result = $roomService->update($roomId, ['status' => $status]);
    
    if ($result) {
        // Log activity
        ActivityLogService::log(
            current_user_id() ?? 'unknown',
            'room_status_updated',
            'Room ' . $roomId . ' status changed to ' . $status,
            ['roomId' => $roomId, 'status' => $status]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Room status updated']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update room']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
