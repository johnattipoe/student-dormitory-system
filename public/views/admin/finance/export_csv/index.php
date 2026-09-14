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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

$houseId = trim((string) ($_GET['houseId'] ?? ''));
$students = StudentService::all();
if ($houseId !== '') {
    $students = StudentService::all($houseId);
}

$paymentRows = [];
try {
    $paymentRows = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
} catch (Throwable $e) {
    $paymentRows = [];
}

$csvData = [];
$csvData[] = ['Student ID', 'Student Name', 'House ID', 'Fee Name', 'Amount', 'Payment Method', 'Status', 'Reference', 'Date'];

foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
    $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
    $studentHouseId = (string) ($student['houseId'] ?? $student['house_id'] ?? '');

    foreach ($paymentRows as $payment) {
        if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
            continue;
        }

        $csvData[] = [
            $studentId,
            $studentName !== '' ? $studentName : 'Student',
            $studentHouseId,
            (string) ($payment['feeName'] ?? 'Boarding Fee'),
            number_format((float) ($payment['amount'] ?? 0), 2, '.', ''),
            (string) ($payment['paymentMethod'] ?? 'Cash'),
            (string) ($payment['status'] ?? 'paid'),
            (string) ($payment['reference'] ?? 'N/A'),
            (string) ($payment['recordedAt'] ?? $payment['createdAt'] ?? date('Y-m-d H:i:s')),
        ];
    }
}

$filename = 'finance-export-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
foreach ($csvData as $row) {
    fputcsv($output, $row);
}
 fclose($output);
 exit;
