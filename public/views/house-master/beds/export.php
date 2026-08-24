<?php
require __DIR__ . '/_context.php';
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="house-beds-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w'); fputcsv($output, ['Bed Number', 'Capacity', 'Room', 'Student', 'Admission No.', 'Status']);
foreach ($beds as $bed) { $room = $roomMap[(string)($bed['roomId']??'')] ?? []; $student = $studentMap[(string)($bed['studentId']??'')] ?? []; fputcsv($output, [$bed['bedNumber']??'', $bed['capacity']??1, $room['roomNumber']??'', trim(($student['firstName']??'').' '.($student['lastName']??'')), $student['admissionNo']??'', $bed['status']??'available']); }
fclose($output); exit;
