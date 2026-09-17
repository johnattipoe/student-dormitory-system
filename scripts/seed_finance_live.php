<?php
require __DIR__ . '/../public/bootstrap.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

$db = FirebaseService::getInstance();
$students = StudentService::all();

$fees = $db->getCollection(COL_FINANCE_FEES, [], 50);
if (empty($fees)) {
    $db->addDocument(COL_FINANCE_FEES, [
        'name' => 'Boarding Fee',
        'amount' => 650.00,
        'period' => 'Monthly',
        'status' => 'active',
    ], 'boarding-fee');

    $db->addDocument(COL_FINANCE_FEES, [
        'name' => 'Utility Fee',
        'amount' => 120.00,
        'period' => 'Monthly',
        'status' => 'active',
    ], 'utility-fee');
    echo "Created default fee categories.\n";
} else {
    echo "Fee categories already exist.\n";
}

$payments = $db->getCollection(COL_FINANCE_PAYMENTS, [], 100);
if (empty($payments)) {
    foreach ($students as $index => $student) {
        $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
        if ($studentId === '') {
            continue;
        }

        $amount = ($index < 2) ? 650.00 : 0.00;
        $status = ($index < 2) ? 'paid' : 'pending';

        $db->addDocument(COL_FINANCE_PAYMENTS, [
            'studentId' => $studentId,
            'studentName' => trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))),
            'houseId' => (string) ($student['houseId'] ?? ''),
            'feeName' => 'Boarding Fee',
            'amount' => $amount,
            'paymentMethod' => 'Cash',
            'reference' => 'SEED-' . strtoupper(substr(md5($studentId . time()), 0, 8)),
            'status' => $status,
            'notes' => 'Seeded live finance record',
            'recordedBy' => 'admin',
            'recordedAt' => date('Y-m-d H:i:s'),
        ], $studentId . '_finance_' . ($index + 1));
    }

    echo "Created live payment records for students.\n";
} else {
    echo "Payment records already exist.\n";
}

echo "Students=" . count($students) . "\n";
echo "Fees=" . count($db->getCollection(COL_FINANCE_FEES, [], 50)) . "\n";
echo "Payments=" . count($db->getCollection(COL_FINANCE_PAYMENTS, [], 100)) . "\n";
