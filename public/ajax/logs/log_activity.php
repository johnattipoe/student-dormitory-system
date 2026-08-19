<?php
require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

use App\Services\ActivityLogService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$action = (string) ($data['action'] ?? 'unknown');
$description = (string) ($data['description'] ?? '');
$meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

$userId = current_user_id() ?? 'unknown';

try {
    ActivityLogService::log($userId, $action, $description, $meta);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
