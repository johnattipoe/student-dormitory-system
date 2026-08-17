<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\IncidentService;
use App\Services\ActivityLogService;

header('Content-Type: application/json');

// Require house-master, houseparent, or admin role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT, ROLE_ADMIN])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$incidentId = sanitize($input['incidentId'] ?? '');
$status = sanitize($input['status'] ?? '');

// Validate required fields
if (empty($incidentId) || empty($status) || !in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid incidentId or status']);
    exit;
}

try {
    $incidentService = new IncidentService();
    
    // Update incident status
    $result = $incidentService->update($incidentId, ['status' => $status]);
    
    if ($result) {
        // Log activity
        (new ActivityLogService())->log(
            'incident_status_updated',
            'Updated incident ' . $incidentId . ' status to ' . $status,
            ['incidentId' => $incidentId, 'status' => $status]
        );
        
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Incident status updated']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update incident']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
