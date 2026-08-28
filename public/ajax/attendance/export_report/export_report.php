<?php
require __DIR__ . '/../../bootstrap.php';

use App\Services\AttendanceService;
use App\Services\StudentService;

// Require house-master role
if (!in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_ADMIN])) {
    http_response_code(403);
    exit;
}

$dateFrom = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo = sanitize($_GET['to'] ?? date('Y-m-d'));
$houseId = sanitize($_GET['houseId'] ?? (current_user()['houseId'] ?? null));
$format = sanitize($_GET['format'] ?? 'csv');

try {
    // Validate dates
    if (!strtotime($dateFrom) || !strtotime($dateTo)) {
        throw new Exception('Invalid date format');
    }
    
    // Get students
    $students = StudentService::all($houseId);
    $studentMap = [];
    foreach ($students as $student) {
        $studentMap[(string) ($student['id'] ?? '')] = $student;
    }
    
    // Generate attendance data for date range
    $attendanceService = new AttendanceService();
    $allAttendance = [];
    
    $current = new DateTime($dateFrom);
    $end = new DateTime($dateTo);
    
    while ($current <= $end) {
        $dateStr = $current->format('Y-m-d');
        $dayAttendance = $attendanceService->forDate($dateStr, $houseId);
        $allAttendance = array_merge($allAttendance, $dayAttendance);
        $current->modify('+1 day');
    }
    
    if ($format === 'csv') {
        // Prepare CSV
        $headers = ['Date', 'Student Name', 'Admission No.', 'Status', 'Marked By'];
        
        // Generate CSV
        $csvContent = fopen('php://memory', 'r+');
        fputcsv($csvContent, $headers);
        
        foreach ($allAttendance as $record) {
            $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
            
            fputcsv($csvContent, [
                $record['date'] ?? '—',
                ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
                $student['admissionNo'] ?? '—',
                $record['status'] ?? 'present',
                $record['markedBy'] ?? '—'
            ]);
        }
        
        rewind($csvContent);
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report_' . $dateFrom . '_to_' . $dateTo . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo stream_get_contents($csvContent);
        fclose($csvContent);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
