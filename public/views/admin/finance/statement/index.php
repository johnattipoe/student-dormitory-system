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

$studentId = trim((string) ($_GET['studentId'] ?? ''));
$student = $studentId !== '' ? StudentService::find($studentId) : null;
if (!$student) {
    flash('error', 'Student not found.');
    redirect(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')));
}

$studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
$payments = [];
try {
    $payments = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
} catch (Throwable $e) {
    $payments = [];
}

$studentPayments = [];
foreach ($payments as $payment) {
    if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
        continue;
    }
    $studentPayments[] = [
        'id' => (string) ($payment['id'] ?? ''),
        'date' => (string) ($payment['recordedAt'] ?? ($payment['createdAt'] ?? date('Y-m-d H:i:s'))),
        'feeName' => (string) ($payment['feeName'] ?? 'Boarding Fee'),
        'amount' => (float) ($payment['amount'] ?? 0),
        'method' => (string) ($payment['paymentMethod'] ?? 'Cash'),
        'reference' => (string) ($payment['reference'] ?? '—'),
    ];
}

$balance = 0.0;
$totalPaid = 0.0;
foreach ($studentPayments as $payment) {
    $totalPaid += (float) $payment['amount'];
}
$balance = max(0.0, 650.0 - $totalPaid);

$pageTitle = 'Student Statement';
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Student Finance Statement</h4>
                <p class="text-muted mb-0">Statement for <?= e($studentName !== '' ? $studentName : $studentId) ?>.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')) ?>" class="btn btn-outline-secondary">Back to Payments</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total Charges</small>
                    <strong class="fs-3 d-block mt-2">GHS 650.00</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total Paid</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($totalPaid, 2) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Outstanding</small>
                    <strong class="fs-3 d-block mt-2">GHS <?= number_format($balance, 2) ?></strong>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="fw-bold mb-0">Payment history</h5>
                    <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/export/index.php') . '&studentId=' . urlencode($studentId) . '&format=pdf') ?>" class="btn btn-outline-danger btn-sm">Download PDF Statement</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Fee</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentPayments as $payment): ?>
                                <tr>
                                    <td><?= e(date('d M Y', strtotime((string) $payment['date']))) ?></td>
                                    <td><?= e($payment['feeName']) ?></td>
                                    <td>GHS <?= number_format((float) $payment['amount'], 2) ?></td>
                                    <td><?= e($payment['method']) ?></td>
                                    <td><?= e($payment['reference']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($studentPayments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No payments recorded for this student yet.</td>
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
