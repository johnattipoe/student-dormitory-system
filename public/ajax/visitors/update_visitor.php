<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\VisitorService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require house-master, houseparent, or admin role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$visitorId = sanitize($input['visitorId'] ?? '');
$status = sanitize($input['status'] ?? '');
$checkInTime = sanitize($input['checkInTime'] ?? null);
$checkOutTime = sanitize($input['checkOutTime'] ?? null);

// Validate required fields
if (empty($visitorId) || empty($status) || !in_array($status, ['pending', 'checked_in', 'checked_out', 'inside'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid visitorId or status']);
    exit;
}

try {
    $visitorService = new VisitorService();
    
    // Prepare update data
    $updateData = ['status' => $status];
    
    if ($status === 'checked_in' && $checkInTime) {
        $updateData['checkInTime'] = $checkInTime;
    } elseif ($status === 'checked_in') {
        $updateData['checkInTime'] = date('Y-m-d H:i:s');
    }
    
    if ($status === 'checked_out' && $checkOutTime) {
        $updateData['checkOutTime'] = $checkOutTime;
    } elseif ($status === 'checked_out') {
        $updateData['checkOutTime'] = date('Y-m-d H:i:s');
    }
    
    // Update visitor status
    $result = $visitorService->update($visitorId, $updateData);
    
    if ($result) {
        // Log activity
        (new ActivityLogService())->log(
            'visitor_status_updated',
            'Visitor ' . $visitorId . ' status changed to ' . $status,
            ['visitorId' => $visitorId, 'status' => $status]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Visitor status updated']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update visitor']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
