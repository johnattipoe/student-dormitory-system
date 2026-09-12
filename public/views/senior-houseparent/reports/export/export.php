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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\UserService;
use App\Services\FirebaseService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$type = sanitize($_GET['type'] ?? $_GET['dataset'] ?? 'senior_houseparent');
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$filename = 'senior-houseparent-' . preg_replace('/[^a-z0-9_-]+/i', '-', $type) . '-' . date('Y-m-d') . '.csv';

// Preload user map for name resolution
$userMap = [];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
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

$resolveUser = function (?string $raw) use (&$userMap): string {
    $raw = (string) $raw;
    if ($raw === '') return '—';
    if (isset($userMap[$raw])) return $userMap[$raw];
    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
    } catch (\Throwable $e) {}
    return $raw;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

if ($type === 'attendance') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) {
        $students[(string) ($student['id'] ?? '')] = $student;
    }
    fputcsv($output, ['Date', 'Student Name', 'Admission No.', 'Class', 'Status', 'Marked By']);
    foreach (AttendanceService::forDate($date, $houseId) as $record) {
        $student = $students[(string) ($record['studentId'] ?? '')] ?? [];
        $markedBy = !empty($record['markedByName']) ? $record['markedByName'] : $resolveUser($record['markedBy'] ?? '');
        fputcsv($output, [
            $record['date'] ?? $date,
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($record['studentName'] ?? '—'),
            $student['admissionNo'] ?? '—',
            $student['class'] ?? $student['level'] ?? '—',
            ucfirst((string) ($record['status'] ?? 'unknown')),
            $markedBy,
        ]);
    }
} elseif ($type === 'occupancy' || $type === 'rooms') {
    fputcsv($output, ['Room Number', 'Type', 'Capacity', 'Occupied Beds', 'Available Beds', 'Occupancy Rate (%)', 'Status']);
    foreach (RoomService::all($houseId) as $room) {
        $capacity = (int) ($room['capacity'] ?? 0);
        $occupied = (int) ($room['occupied'] ?? $room['occupancy'] ?? 0);
        $available = max(0, $capacity - $occupied);
        $rate = $capacity > 0 ? round(($occupied / $capacity) * 100) : 0;
        fputcsv($output, [
            $room['roomNumber'] ?? $room['name'] ?? '',
            ucfirst((string) ($room['type'] ?? 'standard')),
            $capacity,
            $occupied,
            $available,
            $rate . '%',
            ucfirst((string) ($room['status'] ?? 'active')),
        ]);
    }
} elseif ($type === 'students') {
    fputcsv($output, ['Admission No.', 'Full Name', 'Form', 'Class', 'Gender', 'Course', 'Room', 'Status', 'Guardian Name', 'Guardian Phone', 'Guardian Email']);
    foreach (StudentService::all($houseId) as $student) {
        fputcsv($output, [
            $student['admissionNo'] ?? '',
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
            $student['form'] ?? $student['level'] ?? '',
            $student['class'] ?? '',
            $student['gender'] ?? '',
            $student['course'] ?? '',
            $student['roomId'] ?? $student['room'] ?? '',
            ucfirst((string) ($student['status'] ?? 'active')),
            $student['guardianName'] ?? '',
            $student['guardianPhone'] ?? '',
            $student['guardianEmail'] ?? '',
        ]);
    }
} elseif ($type === 'flags') {
    fputcsv($output, ['Admission No.', 'Student Name', 'Flagged', 'Flag Type', 'Reason', 'Flagged At']);
    foreach (StudentService::all($houseId) as $student) {
        fputcsv($output, [
            $student['admissionNo'] ?? '',
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
            !empty($student['flagged']) ? 'Yes' : 'No',
            $student['flagType'] ?? '—',
            $student['flagReason'] ?? '—',
            $student['flaggedAt'] ?? '—',
        ]);
    }
} elseif ($type === 'incidents') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) {
        $students[(string) ($student['id'] ?? '')] = $student;
    }
    fputcsv($output, ['Date Reported', 'Student Name', 'Admission No.', 'Incident Title', 'Priority', 'Status', 'Reported By', 'Details']);
    foreach ((new IncidentService())->byHouse($houseId) as $incident) {
        $student = $students[(string) ($incident['studentId'] ?? '')] ?? [];
        $reporter = !empty($incident['reportedByName']) ? $incident['reportedByName'] : $resolveUser($incident['reportedBy'] ?? '');
        fputcsv($output, [
            substr((string) ($incident['reportedAt'] ?? $incident['createdAt'] ?? '—'), 0, 10),
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($incident['studentName'] ?? '—'),
            $student['admissionNo'] ?? '—',
            $incident['title'] ?? $incident['type'] ?? 'Incident',
            ucfirst((string) ($incident['priority'] ?? $incident['severity'] ?? 'low')),
            ucfirst(str_replace('_', ' ', (string) ($incident['status'] ?? 'open'))),
            $reporter,
            $incident['description'] ?? $incident['details'] ?? '—',
        ]);
    }
} elseif ($type === 'visitors') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) {
        $students[(string) ($student['id'] ?? '')] = $student;
    }
    fputcsv($output, ['Visit Date', 'Visitor Name', 'Phone', 'Student Name', 'Admission No.', 'Relationship', 'Purpose', 'Status', 'Check-in Time', 'Check-out Time']);
    foreach ((new VisitorService())->byHouse($houseId) as $visitor) {
        $student = $students[(string) ($visitor['studentId'] ?? '')] ?? [];
        fputcsv($output, [
            $visitor['visitDate'] ?? substr((string) ($visitor['createdAt'] ?? '—'), 0, 10),
            $visitor['visitorName'] ?? $visitor['name'] ?? 'Visitor',
            $visitor['phone'] ?? $visitor['contact'] ?? '—',
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($visitor['studentId'] ?? '—'),
            $student['admissionNo'] ?? '—',
            $visitor['relationship'] ?? '—',
            $visitor['purpose'] ?? 'General Visit',
            ucwords(str_replace('_', ' ', (string) ($visitor['status'] ?? 'unknown'))),
            $visitor['checkInTime'] ?? $visitor['timeIn'] ?? '—',
            $visitor['checkOutTime'] ?? $visitor['timeOut'] ?? '—',
        ]);
    }
} elseif ($type === 'medical' || $type === 'health') {
    $students = [];
    foreach (StudentService::all($houseId) as $student) {
        $students[(string) ($student['id'] ?? '')] = $student;
    }
    $studentIds = array_fill_keys(array_keys($students), true);
    $records = FirebaseService::getInstance()->getCollection('medical_records', [], 500);
    $records = array_values(array_filter($records, fn($r) => isset($studentIds[(string)($r['studentId'] ?? '')])));
    
    fputcsv($output, ['Date', 'Student Name', 'Admission No.', 'Diagnosis / Condition', 'Treatment', 'Severity', 'Recorded By', 'Notes']);
    foreach ($records as $rec) {
        $student = $students[(string) ($rec['studentId'] ?? '')] ?? [];
        fputcsv($output, [
            substr((string) ($rec['createdAt'] ?? $rec['date'] ?? '—'), 0, 10),
            trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($rec['studentName'] ?? '—'),
            $student['admissionNo'] ?? '—',
            $rec['diagnosis'] ?? $rec['condition'] ?? '—',
            $rec['treatment'] ?? '—',
            ucfirst((string) ($rec['severity'] ?? 'normal')),
            $rec['recordedByName'] ?? $resolveUser($rec['recordedBy'] ?? ''),
            $rec['notes'] ?? $rec['description'] ?? '—',
        ]);
    }
} else {
    // Summary export
    $students = StudentService::all($houseId);
    $summary = AttendanceService::summary($date, $houseId);
    $roomStats = RoomService::occupancyStats($houseId);
    $incidents = (new IncidentService())->byHouse($houseId);
    $visitors = (new VisitorService())->byHouse($houseId);

    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Report Date', $date]);
    fputcsv($output, ['Total Assigned Students', count($students)]);
    fputcsv($output, ['Attendance Present', $summary['present'] ?? 0]);
    fputcsv($output, ['Attendance Absent', $summary['absent'] ?? 0]);
    fputcsv($output, ['Attendance Late', $summary['late'] ?? 0]);
    fputcsv($output, ['Attendance Excused', $summary['excused'] ?? 0]);
    fputcsv($output, ['Total Rooms', $roomStats['rooms'] ?? 0]);
    fputcsv($output, ['Bed Capacity', $roomStats['capacity'] ?? 0]);
    fputcsv($output, ['Occupied Beds', $roomStats['occupied'] ?? 0]);
    fputcsv($output, ['Vacant Beds', $roomStats['vacant'] ?? 0]);
    fputcsv($output, ['Occupancy Rate (%)', ($roomStats['occupancyRate'] ?? 0) . '%']);
    fputcsv($output, ['Total Incidents Logged', count($incidents)]);
    fputcsv($output, ['Total Visitors Logged', count($visitors)]);
}

fclose($output);
exit;

