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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\UserService;

$allLogs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 1000);

usort($allLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

// Preload user map
$userMap = ['default-admin' => 'Administrator (Admin)'];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
        if ($name !== '') {
            $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$u['id'] ?? null, $u['uid'] ?? null, $u['userId'] ?? null, $u['firebaseUid'] ?? null] as $key) {
                if ($key !== null && (string)$key !== '') {
                    $userMap[(string)$key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getUserName = function (array $log) use (&$userMap): string {
    $name = (string) ($log['userName'] ?? $log['performedByName'] ?? '');
    if ($name !== '' && $name !== 'default-admin' && !str_starts_with($name, 'Staff/User')) {
        return $name;
    }
    $raw = (string) ($log['userId'] ?? $log['performedBy'] ?? '');
    if ($raw === 'default-admin') return 'Administrator (Admin)';
    return $userMap[$raw] ?? ($raw ?: 'System');
};

$filename = 'system-activity-logs-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['Timestamp', 'Actor / Staff', 'Action / Event', 'Description / Details', 'House', 'IP Address']);

foreach ($allLogs as $log) {
    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    $formattedTime = $rawTime !== '' ? (date('Y-m-d H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
    fputcsv($output, [
        $formattedTime,
        $getUserName($log),
        ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? $log['type'] ?? 'Activity'))),
        $log['details'] ?? $log['description'] ?? $log['message'] ?? '—',
        $log['houseName'] ?? $log['houseId'] ?? 'General',
        $log['ip'] ?? $log['ipAddress'] ?? '—',
    ]);
}

fclose($output);
exit;

