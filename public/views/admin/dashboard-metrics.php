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

$studentCount      = count(FirebaseService::getInstance()->getCollection(COL_STUDENTS, [], 1000));
$houseCount        = count(FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100));
$roomCount         = count(FirebaseService::getInstance()->getCollection(COL_ROOMS, [], 500));
$incidentCount     = count(FirebaseService::getInstance()->getCollection(COL_INCIDENTS, [], 500));
$attendanceCount   = count(FirebaseService::getInstance()->getCollection(COL_ATTENDANCE, [], 500));
$allocationCount   = count(FirebaseService::getInstance()->getCollection(COL_ROOM_ALLOCATIONS, [], 500));
$activityLogCount  = count(FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500));
$notificationCount = count((new NotificationService())->all());

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
