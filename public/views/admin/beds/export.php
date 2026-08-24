<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$rooms = [];
foreach (RoomService::all() as $room) $rooms[(string) ($room['id'] ?? '')] = $room;
$houses = [];
foreach (HouseService::all() as $house) $houses[(string) ($house['id'] ?? '')] = $house['name'] ?? $house['id'] ?? '';
$students = [];
foreach (StudentService::all() as $student) $students[(string) ($student['id'] ?? '')] = $student;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="beds-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Bed Number', 'Room', 'House', 'Student', 'Admission No.', 'Status']);
foreach (BedService::all() as $bed) {
    $room = $rooms[(string) ($bed['roomId'] ?? '')] ?? [];
    $student = $students[(string) ($bed['studentId'] ?? '')] ?? [];
    fputcsv($output, [
        $bed['bedNumber'] ?? '',
        $room['roomNumber'] ?? $bed['roomId'] ?? '',
        $houses[(string) ($room['houseId'] ?? '')] ?? '',
        trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
        $student['admissionNo'] ?? '',
        $bed['status'] ?? 'available',
    ]);
}
fclose($output);
exit;
