<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\AttendanceService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require admin or house-master role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$studentId = sanitize($input['studentId'] ?? '');
$status = sanitize($input['status'] ?? 'present');
$date = sanitize($input['date'] ?? date('Y-m-d'));
$houseId = sanitize($input['houseId'] ?? (current_user()['houseId'] ?? null));

// Validate required fields
if (empty($studentId) || empty($status) || !in_array($status, ['present', 'absent', 'late'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid studentId or status']);
    exit;
}

try {
    // Check if record exists and update, or create new
    $attendanceService = new AttendanceService();
    
    // Try to find existing record
    $existing = $attendanceService->forStudent($studentId, $date);
    
    $recordId = null;
    if (!empty($existing)) {
        $recordId = $existing[0]['id'] ?? null;
    }
    
    // Mark attendance (create or update)
    $result = $attendanceService->mark(
        $studentId,
        $status,
        $date,
        $houseId,
        current_user_id(),
        current_user()['name'] ?? 'System'
    );
    
    if ($result) {
        // Log activity
        ActivityLogService::log(
            current_user_id() ?? 'unknown',
            'attendance_marked',
            'Marked ' . $status . ' for student ' . $studentId . ' on ' . $date,
            ['studentId' => $studentId, 'status' => $status, 'date' => $date]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Attendance marked', 'recordId' => $recordId]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to mark attendance']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
