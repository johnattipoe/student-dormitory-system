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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$house = $houseId ? HouseService::find((string) $houseId) : null;
$rooms = RoomService::all($houseId);
$roomMap = [];
foreach ($rooms as $room) $roomMap[(string) ($room['id'] ?? '')] = $room;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) $studentMap[(string) ($student['id'] ?? '')] = $student;
$beds = array_values(array_filter(BedService::all(), static fn ($bed) => isset($roomMap[(string) ($bed['roomId'] ?? '')])));

if (!function_exists('house_master_bed_allowed')) {
    function house_master_bed_allowed(?array $room, ?string $houseId): bool
    {
        return $room !== null && (string) ($room['houseId'] ?? '') === (string) $houseId;
    }
}

function house_master_bed_redirect(): void
{
    redirect(url('views/house-master/beds/index.php'));
}
