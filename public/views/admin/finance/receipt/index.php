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

$paymentId = trim((string) ($_GET['paymentId'] ?? ''));
$payment = null;
if ($paymentId !== '') {
    try {
        $records = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
        foreach ($records as $record) {
            if ((string) ($record['id'] ?? '') === $paymentId) {
                $payment = $record;
                break;
            }
        }
    } catch (Throwable $e) {
        $payment = null;
    }
}

if (!$payment) {
    flash('error', 'Payment receipt not found.');
    redirect(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')));
}

$studentId = (string) ($payment['studentId'] ?? $payment['student_id'] ?? '');
$student = StudentService::find($studentId);
$studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));

$pageTitle = 'Payment Receipt';
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Payment Receipt</h4>
                <p class="text-muted mb-0">Official payment record for student finance tracking.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')) ?>" class="btn btn-outline-secondary">Back to Payments</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold">Dormitory Management System</h5>
                        <p class="mb-1">Finance Office</p>
                        <p class="text-muted mb-0">Receipt No: <?= e((string) ($payment['reference'] ?? 'REF-N/A')) ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1"><strong>Date:</strong> <?= e(date('d M Y', strtotime((string) ($payment['recordedAt'] ?? ($payment['createdAt'] ?? date('Y-m-d H:i:s')))))) ?></p>
                        <p class="mb-0"><strong>Status:</strong> <?= e(strtoupper((string) ($payment['status'] ?? 'paid'))) ?></p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width:30%">Student</th>
                                <td><?= e($studentName !== '' ? $studentName : 'Student') ?></td>
                            </tr>
                            <tr>
                                <th>Fee</th>
                                <td><?= e((string) ($payment['feeName'] ?? 'Boarding Fee')) ?></td>
                            </tr>
                            <tr>
                                <th>Amount</th>
                                <td>GHS <?= number_format((float) ($payment['amount'] ?? 0), 2) ?></td>
                            </tr>
                            <tr>
                                <th>Payment Method</th>
                                <td><?= e((string) ($payment['paymentMethod'] ?? 'Cash')) ?></td>
                            </tr>
                            <tr>
                                <th>Reference</th>
                                <td><?= e((string) ($payment['reference'] ?? '—')) ?></td>
                            </tr>
                            <tr>
                                <th>Recorded By</th>
                                <td><?= e((string) ($payment['recordedBy'] ?? 'Admin')) ?></td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td><?= e((string) ($payment['notes'] ?? 'No additional notes')) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
                    <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/export/index.php') . '&paymentId=' . urlencode($paymentId) . '&format=pdf') ?>" class="btn btn-outline-danger">Download PDF</a>
                    <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')) ?>" class="btn btn-outline-secondary">Return</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
