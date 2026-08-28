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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$firebase = FirebaseService::getInstance();
$visitorLogs = $firebase->getCollection('visitors', [], 1000);

usort($visitorLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['checkInTime'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['checkInTime'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$filename = 'security-gate-logs-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['Visit Date', 'Visitor Name', 'Phone', 'Student Visited', 'Relationship', 'Purpose', 'Status', 'Check-in Time', 'Check-out Time', 'Officer']);

foreach ($visitorLogs as $v) {
    fputcsv($output, [
        $v['visitDate'] ?? substr((string)($v['createdAt'] ?? '—'), 0, 10),
        $v['visitorName'] ?? $v['name'] ?? 'Visitor',
        $v['phone'] ?? $v['contact'] ?? '—',
        $v['studentName'] ?? $v['studentId'] ?? 'Student',
        $v['relationship'] ?? '—',
        $v['purpose'] ?? 'General Visit',
        ucwords(str_replace('_', ' ', (string)($v['status'] ?? 'unknown'))),
        $v['checkInTime'] ?? '—',
        $v['checkOutTime'] ?? '—',
        $v['checkedInByName'] ?? 'Security Desk',
    ]);
}

fclose($output);
exit;

