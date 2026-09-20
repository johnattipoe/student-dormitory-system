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
$studentCount = $firebase->count(COL_STUDENTS);
$houseCount = $firebase->count(COL_HOUSES);
$roomCount = $firebase->count(COL_ROOMS);
$incidentCount = $firebase->count(COL_INCIDENTS);
$attendanceCount = $firebase->count(COL_ATTENDANCE);
$allocationCount = $firebase->count(COL_ROOM_ALLOCATIONS);
$activityLogCount = $firebase->count(COL_ACTIVITY_LOGS);
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
