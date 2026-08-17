<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\AttendanceService;
use App\Services\StudentService;

// Require house-master or houseparent role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT, ROLE_ADMIN])) {
    http_response_code(403);
    exit;
}

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = sanitize($_GET['houseId'] ?? (current_role() === ROLE_HOUSEPARENT ? null : (current_user()['houseId'] ?? null)));
$allHouses = (int) ($_GET['all'] ?? 0);

// Houseparent can view all houses if 'all=1'
if (current_role() === ROLE_HOUSEPARENT && !$allHouses) {
    // Show filtered by house
} elseif (current_role() === ROLE_HOUSEPARENT && $allHouses) {
    $houseId = null; // Show all
}

try {
    // Get attendance data
    $attendanceService = new AttendanceService();
    if ($houseId) {
        $attendance = $attendanceService->forDate($date, $houseId);
    } else {
        $attendance = $attendanceService->forDate($date);
    }
    
    // Get students for mapping
    $students = StudentService::all();
    $studentMap = [];
    foreach ($students as $student) {
        $studentMap[(string) ($student['id'] ?? '')] = $student;
    }
    
    // Prepare CSV headers
    $headers = ['Date', 'Student ID', 'Student Name', 'Admission No.', 'House', 'Status', 'Marked By', 'Approved', 'Approved By'];
    
    // Generate CSV
    $csvContent = fopen('php://memory', 'r+');
    fputcsv($csvContent, $headers);
    
    foreach ($attendance as $record) {
        $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
        $houseName = $student['houseId'] ?? '—';
        
        fputcsv($csvContent, [
            $record['date'] ?? $date,
            $record['studentId'] ?? '',
            ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
            $student['admissionNo'] ?? '—',
            $houseName,
            $record['status'] ?? 'present',
            $record['markedBy'] ?? '—',
            ($record['approved'] ?? false) ? 'Yes' : 'No',
            $record['approvedByName'] ?? '—'
        ]);
    }
    
    rewind($csvContent);
    $output = stream_get_clean();
    $output = stream_get_contents($csvContent);
    fclose($csvContent);
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $csvContent = fopen('php://memory', 'r+');
    fputcsv($csvContent, $headers);
    
    foreach ($attendance as $record) {
        $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
        $houseName = $student['houseId'] ?? '—';
        
        fputcsv($csvContent, [
            $record['date'] ?? $date,
            $record['studentId'] ?? '',
            ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
            $student['admissionNo'] ?? '—',
            $houseName,
            $record['status'] ?? 'present',
            $record['markedBy'] ?? '—',
            ($record['approved'] ?? false) ? 'Yes' : 'No',
            $record['approvedByName'] ?? '—'
        ]);
    }
    
    rewind($csvContent);
    echo stream_get_contents($csvContent);
    fclose($csvContent);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
