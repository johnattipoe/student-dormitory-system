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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\HouseService;
use App\Services\StudentService;

require_once APP_ROOT . '/fpdf19/fpdf.php';

$feeCategories = [];
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
} catch (Throwable $e) {
    $feeCategories = [];
}

$baseAmountPerStudent = 0.0;
foreach ($feeCategories as $fee) {
    $feeStatus = strtolower((string) ($fee['status'] ?? 'active'));
    if ($feeStatus !== 'active') {
        continue;
    }
    $baseAmountPerStudent += (float) ($fee['amount'] ?? 0);
}

$students = StudentService::all();
$houses = HouseService::all();
$houseMap = [];
foreach ($houses as $house) {
    $houseId = (string) ($house['id'] ?? $house['houseId'] ?? '');
    if ($houseId !== '') {
        $houseMap[$houseId] = trim((string) ($house['name'] ?? $house['houseName'] ?? 'House ' . $houseId));
    }
}
$paymentRecords = [];
try {
    $paymentRecords = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
} catch (Throwable $e) {
    $paymentRecords = [];
}

$months = [];
foreach (range(0, 5) as $offset) {
    $date = new DateTimeImmutable('-' . $offset . ' month');
    $months[] = $date->format('Y-m');
}
$months = array_values(array_reverse(array_unique($months)));

$houseOrder = [];
foreach ($students as $student) {
    $houseId = (string) ($student['houseId'] ?? $student['house_id'] ?? 'unassigned');
    if (!isset($houseOrder[$houseId])) {
        $houseOrder[$houseId] = $houseMap[$houseId] ?? trim((string) ($student['houseName'] ?? $student['house_name'] ?? 'House ' . $houseId));
    }
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Monthly Dormitory Finance Report', 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(32, 8, 'Month', 1, 0, 'C');
$pdf->Cell(34, 8, 'House', 1, 0, 'C');
$pdf->Cell(22, 8, 'Students', 1, 0, 'C');
$pdf->Cell(30, 8, 'Expected', 1, 0, 'C');
$pdf->Cell(30, 8, 'Collected', 1, 0, 'C');
$pdf->Cell(30, 8, 'Outstanding', 1, 0, 'C');
$pdf->Cell(22, 8, 'Count', 1, 1, 'C');
$pdf->SetFont('Arial', '', 8);

foreach ($months as $monthKey) {
    foreach ($houseOrder as $houseId => $houseName) {
        $studentIds = [];
        foreach ($students as $student) {
            $studentHouseId = (string) ($student['houseId'] ?? $student['house_id'] ?? 'unassigned');
            if ($studentHouseId !== $houseId) {
                continue;
            }
            $studentIds[] = (string) ($student['id'] ?? $student['uid'] ?? '');
        }

        $expected = count($studentIds) * $baseAmountPerStudent;
        $collected = 0.0;
        $paymentCount = 0;

        foreach ($paymentRecords as $payment) {
            $studentId = (string) ($payment['studentId'] ?? $payment['student_id'] ?? '');
            if (!in_array($studentId, $studentIds, true)) {
                continue;
            }

            $paymentDate = (string) ($payment['recordedAt'] ?? $payment['createdAt'] ?? '');
            if (strpos($paymentDate, $monthKey) !== 0) {
                continue;
            }

            $collected += (float) ($payment['amount'] ?? 0);
            $paymentCount++;
        }

        $label = date('F Y', strtotime($monthKey . '-01'));
        $pdf->Cell(32, 7, $label, 1, 0);
        $pdf->Cell(34, 7, substr($houseName !== '' ? $houseName : 'House ' . $houseId, 0, 14), 1, 0);
        $pdf->Cell(22, 7, (string) count($studentIds), 1, 0, 'C');
        $pdf->Cell(30, 7, 'GHS ' . number_format($expected, 2), 1, 0);
        $pdf->Cell(30, 7, 'GHS ' . number_format($collected, 2), 1, 0);
        $pdf->Cell(30, 7, 'GHS ' . number_format(max(0.0, $expected - $collected), 2), 1, 0);
        $pdf->Cell(22, 7, (string) $paymentCount, 1, 1, 'C');
    }
}

$pdf->Output('D', 'monthly-finance-report-' . date('Y-m-d-His') . '.pdf');
