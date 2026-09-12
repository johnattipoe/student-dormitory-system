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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\UserService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\HouseService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

// Collect student and room IDs for house scoping
$houseStudents = StudentService::all($houseId);
$houseStudentIds = [];
foreach ($houseStudents as $st) {
    foreach ([$st['id'] ?? null, $st['studentId'] ?? null, $st['admissionNo'] ?? null, $st['userId'] ?? null, $st['uid'] ?? null] as $key) {
        if ($key !== null && (string) $key !== '') {
            $houseStudentIds[(string) $key] = true;
        }
    }
}

$houseRooms = RoomService::all($houseId);
$houseRoomIds = [];
foreach ($houseRooms as $rm) {
    if (!empty($rm['id'])) {
        $houseRoomIds[(string) $rm['id']] = true;
    }
}

$currUser = current_user();
$currUserIds = array_filter([(string) ($currUser['uid'] ?? ''), (string) ($currUser['id'] ?? '')]);

$allLogs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500);

$logs = array_values(array_filter($allLogs, function ($log) use ($houseId, $houseStudentIds, $currUserIds, $houseRoomIds) {
    if (!empty($log['houseId']) && (string) $log['houseId'] === $houseId) return true;
    if (!empty($log['house_id']) && (string) $log['house_id'] === $houseId) return true;
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId'] as $k) {
        $actorId = (string) ($log[$k] ?? '');
        if ($actorId !== '' && in_array($actorId, $currUserIds, true)) return true;
    }
    foreach (['studentId', 'student_id', 'targetId', 'target_id'] as $k) {
        $targetId = (string) ($log[$k] ?? '');
        if ($targetId !== '' && isset($houseStudentIds[$targetId])) return true;
    }
    foreach (['roomId', 'room_id'] as $k) {
        $roomId = (string) ($log[$k] ?? '');
        if ($roomId !== '' && isset($houseRoomIds[$roomId])) return true;
    }
    return false;
}));

usort($logs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

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
    $name = (string) ($log['performedByName'] ?? $log['userName'] ?? '');
    if ($name !== '' && $name !== 'default-admin' && !str_starts_with($name, 'Staff/User')) {
        return $name;
    }
    $raw = (string) ($log['userId'] ?? $log['performedBy'] ?? '');
    if ($raw === 'default-admin') return 'Administrator (Admin)';
    return $userMap[$raw] ?? ($raw ?: 'House Master');
};

$filename = 'house-activity-logs-' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($houseName)) . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['Timestamp', 'Actor / Staff', 'Action / Event', 'Details & Observations', 'House', 'Target Student', 'Target Room', 'IP Address']);

foreach ($logs as $log) {
    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    $formattedTime = $rawTime !== '' ? (date('Y-m-d H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
    fputcsv($output, [
        $formattedTime,
        $getUserName($log),
        ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? $log['type'] ?? 'Activity'))),
        $log['details'] ?? $log['description'] ?? $log['message'] ?? '—',
        $houseName,
        $log['studentName'] ?? $log['studentId'] ?? '—',
        !empty($log['roomNumber']) ? 'Room ' . $log['roomNumber'] : ($log['roomId'] ?? '—'),
        $log['ip'] ?? $log['ipAddress'] ?? '—',
    ]);
}

fclose($output);
exit;

