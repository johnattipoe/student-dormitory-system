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

use App\Middleware\RoleMiddleware;
use App\Services\FirebaseService;
use App\Services\NotificationService;

RoleMiddleware::allow($allowedRoles);

$firebase = FirebaseService::getInstance();
$countMetric = static function (string $collection) use ($firebase): int {
    try {
        return $firebase->count($collection);
    } catch (\Throwable $exception) {
        error_log('Dashboard metric unavailable (' . $collection . '): ' . $exception->getMessage());
        return 0;
    }
};

$studentCount = $countMetric(COL_STUDENTS);
$houseCount = $countMetric(COL_HOUSES);
$roomCount = $countMetric(COL_ROOMS);
$incidentCount = $countMetric(COL_INCIDENTS);
$attendanceCount = $countMetric(COL_ATTENDANCE);
$allocationCount = $countMetric(COL_ROOM_ALLOCATIONS);
$activityLogCount = $countMetric(COL_ACTIVITY_LOGS);
$notificationCount = (new NotificationService())->count();

$metrics = [
    'students' => $studentCount,
    'houses' => $houseCount,
    'rooms' => $roomCount,
    'incidents' => $incidentCount,
    'attendance' => $attendanceCount,
    'allocations' => $allocationCount,
    'activityLogs' => $activityLogCount,
    'notifications' => $notificationCount,
];

json_success('Dashboard metrics', ['metrics' => $metrics]);
