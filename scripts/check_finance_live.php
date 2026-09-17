<?php
require __DIR__ . '/../public/bootstrap.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

$students = StudentService::all();
$fees = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
$payments = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);

$baseAmount = 0.0;
foreach ($fees as $fee) {
    if (strtolower((string) ($fee['status'] ?? 'active')) !== 'active') {
        continue;
    }
    $baseAmount += (float) ($fee['amount'] ?? 0);
}

$totalHouseCharges = 0.0;
$collected = 0.0;
$outstanding = 0.0;
$pendingCount = 0;

foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
    $studentPaid = 0.0;
    foreach ($payments as $payment) {
        if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
            continue;
        }
        $studentPaid += (float) ($payment['amount'] ?? 0);
    }

    $totalHouseCharges += $baseAmount;
    $collected += $studentPaid;
    $remaining = max(0.0, $baseAmount - $studentPaid);
    $outstanding += $remaining;
    if ($remaining > 0) {
        $pendingCount++;
    }
}

echo 'students=' . count($students) . PHP_EOL;
echo 'fees=' . count($fees) . PHP_EOL;
echo 'payments=' . count($payments) . PHP_EOL;
echo 'base_amount=' . number_format($baseAmount, 2) . PHP_EOL;
echo 'total_house_charges=' . number_format($totalHouseCharges, 2) . PHP_EOL;
echo 'collected=' . number_format($collected, 2) . PHP_EOL;
echo 'outstanding=' . number_format($outstanding, 2) . PHP_EOL;
echo 'pending=' . $pendingCount . PHP_EOL;
