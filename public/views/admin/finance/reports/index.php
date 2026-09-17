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

$pageTitle = 'Monthly Finance Reports';
$feeCategories = [];
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
} catch (Throwable $e) {
    $feeCategories = [];
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

$baseAmountPerStudent = 0.0;
foreach ($feeCategories as $fee) {
    $feeStatus = strtolower((string) ($fee['status'] ?? 'active'));
    if ($feeStatus !== 'active') {
        continue;
    }
    $baseAmountPerStudent += (float) ($fee['amount'] ?? 0);
}

$houseReportRows = [];
$houseOrder = [];
foreach ($students as $student) {
    $houseId = (string) ($student['houseId'] ?? $student['house_id'] ?? 'unassigned');
    if (!isset($houseOrder[$houseId])) {
        $houseOrder[$houseId] = $houseMap[$houseId] ?? trim((string) ($student['houseName'] ?? $student['house_name'] ?? 'House ' . $houseId));
    }
}

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

        $houseReportRows[] = [
            'month' => date('F Y', strtotime($monthKey . '-01')),
            'houseId' => $houseId,
            'houseName' => $houseName !== '' ? $houseName : ($houseMap[$houseId] ?? 'House ' . $houseId),
            'students' => count($studentIds),
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => max(0.0, $expected - $collected),
            'count' => $paymentCount,
        ];
    }
}

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Monthly Finance Reports</h4>
                <p class="text-muted mb-0">Student payment overview by month based on live finance records.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/index/index.php')) ?>" class="btn btn-outline-secondary">Back to Finance</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="d-flex justify-content-end p-3 border-bottom">
                    <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/export_report/index.php')) ?>" class="btn btn-outline-danger btn-sm">Download PDF</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th>House</th>
                                <th>Students</th>
                                <th>Expected</th>
                                <th>Collected</th>
                                <th>Outstanding</th>
                                <th>Payments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($houseReportRows as $row): ?>
                                <tr>
                                    <td><?= e($row['month']) ?></td>
                                    <td><?= e($row['houseName']) ?></td>
                                    <td><?= (int) $row['students'] ?></td>
                                    <td>GHS <?= number_format((float) $row['expected'], 2) ?></td>
                                    <td>GHS <?= number_format((float) $row['collected'], 2) ?></td>
                                    <td>GHS <?= number_format((float) $row['outstanding'], 2) ?></td>
                                    <td><?= (int) $row['count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($houseReportRows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No payment records available yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
