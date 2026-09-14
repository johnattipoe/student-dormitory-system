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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

if (!can('finance', 'view') && !finance_access_allowed()) {
    http_response_code(403);
    include __DIR__ . '/../../../views/errors/403.php';
    exit;
}

$pageTitle = 'Finance Overview';
$houseId = current_house_id();
$defaultFeeCategories = [
    ['id' => 'boarding-fee', 'name' => 'Boarding Fee', 'amount' => 650.00, 'period' => 'Monthly', 'status' => 'active'],
    ['id' => 'utility-fee', 'name' => 'Utility Fee', 'amount' => 120.00, 'period' => 'Monthly', 'status' => 'active'],
];

$feeCategories = $defaultFeeCategories;
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
    if (empty($feeCategories)) {
        $feeCategories = $defaultFeeCategories;
    }
} catch (Throwable $e) {
    $feeCategories = $defaultFeeCategories;
}

$payments = [];
try {
    $payments = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
} catch (Throwable $e) {
    $payments = [];
}

$baseFee = 0.0;
foreach ($feeCategories as $fee) {
    $baseFee += (float) ($fee['amount'] ?? 0);
}
if ($baseFee <= 0) {
    $baseFee = 650.0;
}

$students = StudentService::all($houseId);
$totalBalances = 0.0;
$totalReceived = 0.0;
$totalOutstanding = 0.0;
$ledgerRows = [];
foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
    $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
    $studentPaid = 0.0;
    $paymentCount = 0;
    foreach ($payments as $payment) {
        if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
            continue;
        }
        $studentPaid += (float) ($payment['amount'] ?? 0);
        $paymentCount++;
    }

    $totalBalances += $baseFee;
    $totalReceived += $studentPaid;
    $totalOutstanding += max(0.0, $baseFee - $studentPaid);

    $ledgerRows[] = [
        'student' => $studentName !== '' ? $studentName : 'Student',
        'studentId' => $studentId,
        'amount' => $baseFee,
        'paid' => $studentPaid,
        'outstanding' => max(0.0, $baseFee - $studentPaid),
        'payments' => $paymentCount,
    ];
}

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Finance Overview</h4>
                <p class="text-muted mb-0">Admin-approved finance data for the assigned house.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Student Balances</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($totalBalances, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Payments Received</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($totalReceived, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Outstanding</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($totalOutstanding, 2) ?></strong>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="fw-bold mb-0">House payment ledger</h5>
                    <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/export_csv/index.php') . '&houseId=' . urlencode((string) $houseId)) ?>" class="btn btn-outline-success btn-sm">Export CSV</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Balance</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                                <th>Payments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ledgerRows as $row): ?>
                                <tr>
                                    <td><?= e($row['student']) ?></td>
                                    <td>GHS <?= number_format((float) $row['amount'], 2) ?></td>
                                    <td>GHS <?= number_format((float) $row['paid'], 2) ?></td>
                                    <td>GHS <?= number_format((float) $row['outstanding'], 2) ?></td>
                                    <td><?= (int) $row['payments'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ledgerRows)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No students are assigned to this house yet.</td>
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
