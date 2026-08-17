<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\AttendanceService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require houseparent or admin role
if (!in_array(current_role(), [ROLE_HOUSEPARENT, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$recordId = sanitize($input['recordId'] ?? '');
$approved = (bool) ($input['approved'] ?? false);

// Validate required fields
if (empty($recordId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid recordId']);
    exit;
}

try {
    $attendanceService = new AttendanceService();
    
    // Update attendance record with approval status
    $updateData = [
        'approved' => $approved,
        'approvedBy' => current_user_id(),
        'approvedByName' => current_user()['name'] ?? 'System',
        'approvedAt' => date('Y-m-d H:i:s')
    ];
    
    if (!$approved) {
        // If rejecting, add rejection reason capability
        $updateData['status'] = 'rejected';
        $updateData['rejectionReason'] = sanitize($input['rejectionReason'] ?? 'Rejected by supervisor');
    }
    
    $result = $attendanceService->update($recordId, $updateData);
    
    if ($result) {
        // Log activity
        $action = $approved ? 'attendance_approved' : 'attendance_rejected';
        (new ActivityLogService())->log(
            $action,
            'Attendance record ' . $recordId . ' ' . ($approved ? 'approved' : 'rejected'),
            ['recordId' => $recordId, 'approved' => $approved]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Attendance ' . ($approved ? 'approved' : 'rejected')]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update attendance']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
