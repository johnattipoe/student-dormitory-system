<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$type = sanitize($_GET['type'] ?? $_GET['dataset'] ?? 'house_master');
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$filename = 'house-master-' . preg_replace('/[^a-z0-9_-]+/i', '-', $type) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fputcsv($output, ['House Master Export', $type, 'Date', $date]);

if ($type === 'attendance') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) $students[(string) ($student['id'] ?? '')] = $student;
    fputcsv($output, ['Date', 'Student', 'Admission No.', 'Status', 'Marked By']);
    foreach (AttendanceService::forDate($date, $houseId) as $record) {
        $student = $students[(string) ($record['studentId'] ?? '')] ?? [];
        fputcsv($output, [$record['date'] ?? $date, trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), $student['admissionNo'] ?? '', $record['status'] ?? '', $record['markedBy'] ?? '']);
    }
} elseif ($type === 'occupancy' || $type === 'rooms') {
    fputcsv($output, ['Room', 'Type', 'Capacity', 'Occupied', 'Available', 'Status']);
    foreach (RoomService::all($houseId) as $room) {
        $capacity = (int) ($room['capacity'] ?? 0);
        $occupied = (int) ($room['occupied'] ?? $room['occupancy'] ?? 0);
        fputcsv($output, [$room['roomNumber'] ?? '', $room['type'] ?? 'standard', $capacity, $occupied, max(0, $capacity - $occupied), $room['status'] ?? '']);
    }
} elseif ($type === 'students') {
    fputcsv($output, ['Admission No.', 'Name', 'Email', 'Phone', 'Course', 'Level', 'Room', 'Status']);
    foreach (StudentService::all($houseId) as $student) {
        fputcsv($output, [$student['admissionNo'] ?? '', trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), $student['email'] ?? '', $student['phone'] ?? '', $student['course'] ?? '', $student['level'] ?? '', $student['roomId'] ?? '', $student['status'] ?? '']);
    }
} elseif ($type === 'flags') {
    fputcsv($output, ['Admission No.', 'Name', 'Flagged', 'Flag Type', 'Reason', 'Flagged At']);
    foreach (StudentService::all($houseId) as $student) {
        fputcsv($output, [$student['admissionNo'] ?? '', trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), !empty($student['flagged']) ? 'Yes' : 'No', $student['flagType'] ?? '', $student['flagReason'] ?? '', $student['flaggedAt'] ?? '']);
    }
} elseif ($type === 'visitors') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) $students[(string) ($student['id'] ?? '')] = $student;
    fputcsv($output, ['Visitor', 'Student', 'Phone', 'Purpose', 'Status', 'Visit Date']);
    foreach ((new VisitorService())->byHouse($houseId) as $visitor) {
        $student = $students[(string) ($visitor['studentId'] ?? '')] ?? [];
        fputcsv($output, [$visitor['visitorName'] ?? '', trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), $visitor['phone'] ?? '', $visitor['purpose'] ?? '', $visitor['status'] ?? '', $visitor['visitDate'] ?? substr((string) ($visitor['createdAt'] ?? ''), 0, 10)]);
    }
} elseif ($type === 'incidents') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) $students[(string) ($student['id'] ?? '')] = $student;
    fputcsv($output, ['Student', 'Title', 'Priority', 'Status', 'Description', 'Reported']);
    foreach ((new IncidentService())->byHouse($houseId) as $incident) {
        $student = $students[(string) ($incident['studentId'] ?? '')] ?? [];
        fputcsv($output, [trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), $incident['title'] ?? $incident['type'] ?? '', $incident['priority'] ?? $incident['severity'] ?? 'low', $incident['status'] ?? 'open', $incident['description'] ?? $incident['notes'] ?? '', $incident['reportedAt'] ?? $incident['createdAt'] ?? '']);
    }
} else {
    $summary = AttendanceService::summary($date, $houseId);
    $roomStats = RoomService::occupancyStats($houseId);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Students', count(StudentService::all($houseId))]);
    fputcsv($output, ['Attendance Total', $summary['total'] ?? 0]);
    fputcsv($output, ['Present', $summary['present'] ?? 0]);
    fputcsv($output, ['Absent', $summary['absent'] ?? 0]);
    fputcsv($output, ['Late', $summary['late'] ?? 0]);
    fputcsv($output, ['Rooms', $roomStats['rooms'] ?? 0]);
    fputcsv($output, ['Room Capacity', $roomStats['capacity'] ?? 0]);
    fputcsv($output, ['Room Occupied', $roomStats['occupied'] ?? 0]);
    fputcsv($output, ['Room Vacant', $roomStats['vacant'] ?? 0]);
}

fclose($output);
exit;
