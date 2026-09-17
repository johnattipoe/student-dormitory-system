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
use App\Services\StudentService;

$pageTitle = 'Finance Dashboard';

$feeCategories = [];
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
} catch (Throwable $e) {
    $feeCategories = [];
}

$students = StudentService::all();
$paymentRecords = [];
try {
    $paymentRecords = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
} catch (Throwable $e) {
    $paymentRecords = [];
}

$totalHouseCharges = 0.0;
$collected = 0.0;
$outstanding = 0.0;
$pendingCount = 0;
$currentMonthCollected = 0.0;
$currentMonthPayments = 0;

$baseAmount = 0.0;
foreach ($feeCategories as $fee) {
    $feeStatus = strtolower((string) ($fee['status'] ?? 'active'));
    if ($feeStatus !== 'active') {
        continue;
    }
    $baseAmount += (float) ($fee['amount'] ?? 0);
}

$currentMonth = date('Y-m');

foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
    $studentExpected = $baseAmount;
    $studentPaid = 0.0;
    foreach ($paymentRecords as $payment) {
        if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
            continue;
        }
        $studentPaid += (float) ($payment['amount'] ?? 0);

        $paymentDate = (string) ($payment['recordedAt'] ?? $payment['createdAt'] ?? '');
        if ($paymentDate !== '' && strpos($paymentDate, $currentMonth) === 0) {
            $currentMonthCollected += (float) ($payment['amount'] ?? 0);
            $currentMonthPayments++;
        }
    }

    $totalHouseCharges += $studentExpected;
    $collected += $studentPaid;
    $remaining = max(0.0, $studentExpected - $studentPaid);
    $outstanding += $remaining;
    if ($remaining > 0) {
        $pendingCount++;
    }
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('index.php?route=' . urlencode('/views/admin/dashboard.php'))],
    ['icon' => 'bi-currency-dollar', 'label' => 'Finance', 'href' => url('index.php?route=' . urlencode('/views/admin/finance/index/index.php')), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Finance Dashboard</h4>
                <p class="text-muted mb-0">Live overview from students, housing records, and finance collections in Firestore.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total House Charges</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($totalHouseCharges, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Collected</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($collected, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Outstanding</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($outstanding, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Pending Payment</small>
                    <strong class="fs-3 d-block mt-2"><?= (int) $pendingCount ?></strong>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">This Month Collected</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($currentMonthCollected, 2) ?></strong>
                    <small class="text-muted mt-2 d-block"><?= (int) $currentMonthPayments ?> payment records</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Monthly Status</small>
                    <strong class="fs-3 d-block mt-2"><?= $currentMonthCollected > 0 ? 'Active' : 'No Data' ?></strong>
                    <small class="text-muted mt-2 d-block">Month: <?= date('F Y') ?></small>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Finance Controls</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')) ?>" class="btn btn-outline-primary w-100">Fee Categories</a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')) ?>" class="btn btn-outline-primary w-100">Student Payments</a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/access/index.php')) ?>" class="btn btn-outline-primary w-100">Access Control</a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/reports/index.php')) ?>" class="btn btn-outline-info w-100">Monthly Reports</a>
                    </div>
                    <div class="col-md-3 mt-3">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/export_csv/index.php')) ?>" class="btn btn-outline-success w-100">Export CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
