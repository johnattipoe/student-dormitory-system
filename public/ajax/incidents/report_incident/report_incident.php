<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\IncidentService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require house-master or admin role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$studentId = sanitize($input['studentId'] ?? '');
$type = sanitize($input['type'] ?? '');
$severity = sanitize($input['severity'] ?? 'normal');
$description = sanitize($input['description'] ?? '');
$houseId = sanitize($input['houseId'] ?? (current_user()['houseId'] ?? null));

// Validate required fields
if (empty($studentId) || empty($type) || empty($description)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate type and severity
$validTypes = ['discipline', 'medical', 'safety', 'property', 'other'];
$validSeverities = ['low', 'normal', 'high', 'critical'];

if (!in_array($type, $validTypes) || !in_array($severity, $validSeverities)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid type or severity']);
    exit;
}

try {
    $incidentService = new IncidentService();
    
    // Create incident
    $incidentData = [
        'studentId' => $studentId,
        'houseId' => $houseId,
        'type' => $type,
        'severity' => $severity,
        'description' => $description,
        'status' => 'open',
        'reportedBy' => current_user_id(),
        'reportedByName' => current_user()['name'] ?? 'System',
        'createdAt' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d')
    ];
    
    $incidentId = $incidentService->create($incidentData);
    
    if ($incidentId) {
        // Log activity
        ActivityLogService::log(
            current_user_id() ?? 'unknown',
            'incident_reported',
            'New incident reported - Type: ' . $type . ', Severity: ' . $severity,
            ['studentId' => $studentId, 'type' => $type, 'severity' => $severity, 'incidentId' => $incidentId]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Incident reported', 'incidentId' => $incidentId]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to report incident']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
