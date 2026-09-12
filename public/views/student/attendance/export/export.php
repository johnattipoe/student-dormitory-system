<?php
// Ensure bootstrap is loaded
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

$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;

// Get export parameters
$format = sanitize($_GET['format'] ?? 'csv');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

// Get student's attendance
$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$attendance = $studentId ? AttendanceService::history($studentId, 1000) : [];

// Apply filters
if (!empty($dateFrom)) {
    $attendance = array_filter($attendance, fn($r) => strtotime($r['date'] ?? '') >= strtotime($dateFrom));
}
if (!empty($dateTo)) {
    $attendance = array_filter($attendance, fn($r) => strtotime($r['date'] ?? '') <= strtotime($dateTo));
}
if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($r) => ($r['status'] ?? 'present') === $statusFilter);
}

// Sort by date descending
usort($attendance, fn($a, $b) => strtotime(($b['date'] ?? '')) <=> strtotime(($a['date'] ?? '')));

$studentName = current_user()['name'] ?? 'Student';
$exportDate = date('Y-m-d H:i:s');

if ($format === 'pdf') {
    require APP_ROOT . '/fpdf19/fpdf.php';
    
    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Student: ' . htmlspecialchars($studentName), 0, 1);
    $pdf->Cell(0, 5, 'Generated: ' . $exportDate, 0, 1);
    $pdf->Ln(5);
    
    // Summary
    $summary = [
        'present' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'present')),
        'absent' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'absent')),
        'late' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'late')),
        'excused' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'excused')),
    ];
    $total = count($attendance);
    $rate = $total > 0 ? round(((($summary['present'] + $summary['excused']) / $total) * 100)) : 0;
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Present:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(30, 7, (string) $summary['present'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Absent:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(30, 7, (string) $summary['absent'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Late:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(30, 7, (string) $summary['late'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Attendance Rate:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(30, 7, $rate . '%', 0, 1);
    
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 7, 'Date', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Status', 1, 0, 'C');
    $pdf->Cell(110, 7, 'Reason/Notes', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('Arial', '', 9);
    foreach ($attendance as $entry) {
        $pdf->Cell(40, 6, substr($entry['date'] ?? '', 0, 10), 1, 0);
        $pdf->Cell(40, 6, ucfirst($entry['status'] ?? 'unknown'), 1, 0);
        $reason = substr($entry['reason'] ?? $entry['notes'] ?? '', 0, 80);
        $pdf->Cell(110, 6, $reason, 1, 1);
    }
    
    $pdf->Output('D', 'attendance_' . date('Y-m-d') . '.pdf');
    exit;
    
} else {
    // CSV export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Date', 'Status', 'Reason/Notes']);
    
    // Data
    foreach ($attendance as $entry) {
        fputcsv($output, [
            $entry['date'] ?? '',
            ucfirst($entry['status'] ?? 'unknown'),
            $entry['reason'] ?? $entry['notes'] ?? '',
        ]);
    }
    
    fclose($output);
    exit;
}
