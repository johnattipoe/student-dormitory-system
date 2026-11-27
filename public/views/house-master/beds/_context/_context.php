<?php
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

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$house = $houseId ? HouseService::find((string) $houseId) : null;
$rooms = $houseId ? RoomService::all($houseId) : [];
$roomMap = [];
foreach ($rooms as $room) $roomMap[(string) ($room['id'] ?? '')] = $room;
$beds = BedService::forRooms(array_keys($roomMap));
$studentMap = [];
foreach ($beds as $bed) {
    $studentId = trim((string) ($bed['studentId'] ?? ''));
    if ($studentId === '' || isset($studentMap[$studentId]) || !$houseId) {
        continue;
    }

    $student = StudentService::find($studentId);
    if ($student && (string) ($student['houseId'] ?? '') === (string) $houseId) {
        $studentMap[$studentId] = $student;
    }
}

if (!function_exists('house_master_bed_allowed')) {
    function house_master_bed_allowed(?array $room, ?string $houseId): bool
    {
        return $room !== null && (string) ($room['houseId'] ?? '') === (string) $houseId;
    }
}

function house_master_bed_redirect(): void
{
    redirect(url('views/house-master/beds/index/index.php'));
}
