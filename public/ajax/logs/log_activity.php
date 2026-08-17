<?php
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../app/config/constants.php';
$appConfig = require __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

use App\Services\ActivityLogService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_role('admin');

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
