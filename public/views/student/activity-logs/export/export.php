<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$studentId = (string) ($user['studentId'] ?? $userId);

$firebase = FirebaseService::getInstance();
$allLogs = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 500);

$myLogs = array_values(array_filter($allLogs, function ($log) use ($userId, $studentId) {
    foreach (['studentId', 'student_id', 'targetId', 'target_id', 'userId', 'user_id'] as $k) {
        $val = (string) ($log[$k] ?? '');
        if ($val !== '' && ($val === $userId || $val === $studentId)) {
            return true;
        }
    }
    return false;
}));

usort($myLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$filename = 'my-activity-history-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['Timestamp', 'Activity Event', 'Details', 'Recorded By']);

foreach ($myLogs as $log) {
    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
    $formattedTime = $rawTime !== '' ? (date('Y-m-d H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
    fputcsv($output, [
        $formattedTime,
        ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? 'Activity'))),
        $log['details'] ?? $log['description'] ?? '—',
        $log['performedByName'] ?? 'Staff',
    ]);
}

fclose($output);
exit;

