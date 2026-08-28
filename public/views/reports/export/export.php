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

use App\Services\AttendanceService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\IncidentService;

$format = sanitize($_GET['format'] ?? 'csv');
$type = sanitize($_GET['type'] ?? 'dashboard');
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;

$filename = 'senior-houseparent_' . $type . '_' . date('Y-m-d_His');

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'attendance') {
        // Attendance CSV
        fputcsv($output, ['Date', 'Student Name', 'Admission No.', 'Status', 'Notes', 'Marked By']);
        
        $attendance = AttendanceService::forDate($date, $houseId);
        $students = StudentService::all($houseId);
        $studentMap = [];
        foreach ($students as $s) {
            $studentMap[(string) ($s['id'] ?? '')] = $s;
        }
        
        foreach ($attendance as $record) {
            $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
            fputcsv($output, [
                $record['date'] ?? '',
                ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
                $student['admissionNo'] ?? '',
                $record['status'] ?? 'present',
                $record['notes'] ?? '',
                $record['markedBy'] ?? '',
            ]);
        }
    } elseif ($type === 'visitors') {
        // Visitor CSV
        fputcsv($output, ['Visitor Name', 'Student', 'Admission No.', 'Visit Date', 'Status', 'Purpose']);
        
        $visitors = (new VisitorService())->byHouse($houseId);
        $students = StudentService::all($houseId);
        $studentMap = [];
        foreach ($students as $s) {
            $studentMap[(string) ($s['id'] ?? '')] = $s;
        }
        
        foreach ($visitors as $visitor) {
            $student = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null;
            fputcsv($output, [
                $visitor['visitorName'] ?? '',
                ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
                $student['admissionNo'] ?? '',
                $visitor['visitDate'] ?? $visitor['checkInTime'] ?? '',
                $visitor['status'] ?? 'pending',
                $visitor['purpose'] ?? '',
            ]);
        }
    } elseif ($type === 'incidents') {
        // Incidents CSV
        fputcsv($output, ['Date', 'Student', 'Admission No.', 'Type', 'Description', 'Status', 'Reported By']);
        
        $incidents = (new IncidentService())->byHouse($houseId);
        $students = StudentService::all($houseId);
        $studentMap = [];
        foreach ($students as $s) {
            $studentMap[(string) ($s['id'] ?? '')] = $s;
        }
        
        foreach ($incidents as $incident) {
            $student = $studentMap[(string) ($incident['studentId'] ?? '')] ?? null;
            fputcsv($output, [
                $incident['date'] ?? '',
                ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''),
                $student['admissionNo'] ?? '',
                $incident['type'] ?? '',
                $incident['description'] ?? '',
                $incident['status'] ?? 'open',
                $incident['reportedBy'] ?? '',
            ]);
        }
    }
    
    fclose($output);
    exit;
} elseif ($format === 'pdf') {
    // PDF Export using FPDF
    require APP_ROOT . '/fpdf19/fpdf.php';
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Houseparent ' . ucfirst($type) . ' Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, 'Date: ' . $date, 0, 1);
    $pdf->Ln(5);
    
    if ($type === 'attendance') {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(30, 7, 'Date', 1);
        $pdf->Cell(50, 7, 'Student', 1);
        $pdf->Cell(30, 7, 'Adm. No.', 1);
        $pdf->Cell(30, 7, 'Status', 1);
        $pdf->Cell(30, 7, 'Notes', 1);
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 9);
        $attendance = AttendanceService::forDate($date, $houseId);
        $students = StudentService::all($houseId);
        $studentMap = [];
        foreach ($students as $s) {
            $studentMap[(string) ($s['id'] ?? '')] = $s;
        }
        
        foreach ($attendance as $record) {
            $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
            $pdf->Cell(30, 6, $record['date'] ?? '', 1);
            $pdf->Cell(50, 6, substr(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''), 0, 20), 1);
            $pdf->Cell(30, 6, $student['admissionNo'] ?? '', 1);
            $pdf->Cell(30, 6, $record['status'] ?? 'present', 1);
            $pdf->Cell(30, 6, substr($record['notes'] ?? '', 0, 10), 1);
            $pdf->Ln();
        }
    }
    
    $pdf->Output('D', $filename . '.pdf');
    exit;
}
