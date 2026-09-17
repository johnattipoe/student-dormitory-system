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

$pageTitle = 'Student Payments';
$currentRole = current_role();
$isHouseScoped = in_array($currentRole, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true);
$assignedHouseId = current_house_id();
if ($isHouseScoped && !finance_access_allowed()) {
    http_response_code(403);
    include APP_ROOT . '/public/views/errors/403.php';
    exit;
}
$paymentRoute = '/views/admin/finance/payments/index.php';
$paymentPageUrl = url('index.php?route=' . urlencode($paymentRoute));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    if ($action === 'delete') {
        $paymentId = trim((string) ($_POST['paymentId'] ?? ''));
        if ($paymentId !== '') {
            try {
                $payment = FirebaseService::getInstance()->getDocument(COL_FINANCE_PAYMENTS, $paymentId);
                if ($isHouseScoped && (!$payment || (string) ($payment['houseId'] ?? '') !== (string) $assignedHouseId)) {
                    throw new RuntimeException('You can only manage payments for your assigned house.');
                }
                FirebaseService::getInstance()->deleteDocument(COL_FINANCE_PAYMENTS, $paymentId);
                flash('success', 'Payment deleted successfully.');
            } catch (Throwable $e) {
                flash('error', 'Unable to delete payment: ' . $e->getMessage());
            }
        }
        redirect($paymentPageUrl);
    }

    if ($action === 'update') {
        $paymentId = trim((string) ($_POST['paymentId'] ?? ''));
        $studentId = trim((string) ($_POST['studentId'] ?? ''));
        $feeName = trim((string) ($_POST['feeName'] ?? 'Boarding Fee'));
        $amount = (float) ($_POST['amount'] ?? 0);
        $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Cash'));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $status = strtolower(trim((string) ($_POST['status'] ?? 'paid')));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($paymentId !== '' && $studentId !== '' && $amount > 0) {
            $student = StudentService::find($studentId);
            if ($isHouseScoped && (!$student || (string) ($student['houseId'] ?? '') !== (string) $assignedHouseId)) {
                flash('error', 'You can only manage students in your assigned house.');
                redirect($paymentPageUrl);
            }
            $payload = [
                'studentId' => $studentId,
                'studentName' => trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))),
                'houseId' => (string) ($student['houseId'] ?? ''),
                'feeName' => $feeName,
                'amount' => $amount,
                'paymentMethod' => $paymentMethod,
                'reference' => $reference !== '' ? $reference : ('REF-' . strtoupper(bin2hex(random_bytes(4)))),
                'status' => in_array($status, ['paid', 'pending', 'partial'], true) ? $status : 'paid',
                'notes' => $notes,
                'recordedBy' => current_user_id() ?: 'admin',
                'recordedAt' => date('Y-m-d H:i:s'),
            ];

            try {
                FirebaseService::getInstance()->updateDocument(COL_FINANCE_PAYMENTS, $paymentId, $payload);
                flash('success', 'Payment updated successfully.');
            } catch (Throwable $e) {
                flash('error', 'Unable to update payment: ' . $e->getMessage());
            }
            redirect($paymentPageUrl);
        }
    }

    $studentId = trim((string) ($_POST['studentId'] ?? ''));
    $feeName = trim((string) ($_POST['feeName'] ?? 'Boarding Fee'));
    $amount = (float) ($_POST['amount'] ?? 0);
    $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Cash'));
    $reference = trim((string) ($_POST['reference'] ?? ''));
    $status = strtolower(trim((string) ($_POST['status'] ?? 'paid')));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($studentId !== '' && $amount > 0) {
        $student = StudentService::find($studentId);
        if ($isHouseScoped && (!$student || (string) ($student['houseId'] ?? '') !== (string) $assignedHouseId)) {
            flash('error', 'You can only collect payments for students in your assigned house.');
            redirect($paymentPageUrl);
        }
        $payload = [
            'studentId' => $studentId,
            'studentName' => trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))),
            'houseId' => (string) ($student['houseId'] ?? ''),
            'feeName' => $feeName,
            'amount' => $amount,
            'paymentMethod' => $paymentMethod,
            'reference' => $reference !== '' ? $reference : ('REF-' . strtoupper(bin2hex(random_bytes(4)))),
            'status' => in_array($status, ['paid', 'pending', 'partial'], true) ? $status : 'paid',
            'notes' => $notes,
            'recordedBy' => current_user_id() ?: 'admin',
            'recordedAt' => date('Y-m-d H:i:s'),
        ];

        try {
            FirebaseService::getInstance()->addDocument(COL_FINANCE_PAYMENTS, $payload, $studentId . '_' . time());
            flash('success', 'Payment recorded successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to record payment: ' . $e->getMessage());
        }
        redirect($paymentPageUrl);
    } else {
        flash('error', 'Please select a student and enter a valid amount.');
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));

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

$paymentRecords = [];
try {
    $paymentRecords = FirebaseService::getInstance()->getCollection(COL_FINANCE_PAYMENTS, [], 1000);
    if ($isHouseScoped) {
        $paymentRecords = array_values(array_filter($paymentRecords, static fn(array $payment): bool => (string) ($payment['houseId'] ?? '') === (string) $assignedHouseId));
    }
} catch (Throwable $e) {
    $paymentRecords = [];
}

$actualPaymentRows = [];
foreach ($paymentRecords as $payment) {
    $studentId = (string) ($payment['studentId'] ?? $payment['student_id'] ?? '');
    $student = StudentService::find($studentId);
    $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
    $actualPaymentRows[] = [
        'id' => (string) ($payment['id'] ?? $studentId . '-' . time()),
        'studentId' => $studentId,
        'studentName' => $studentName !== '' ? $studentName : 'Student',
        'feeName' => (string) ($payment['feeName'] ?? 'Boarding Fee'),
        'amount' => (float) ($payment['amount'] ?? 0),
        'status' => strtolower((string) ($payment['status'] ?? 'paid')),
        'method' => (string) ($payment['paymentMethod'] ?? 'Cash'),
        'reference' => (string) ($payment['reference'] ?? '—'),
        'date' => (string) ($payment['recordedAt'] ?? ($payment['createdAt'] ?? date('Y-m-d H:i:s'))),
    ];
}

$houses = [];
try {
    $houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 200);
} catch (Throwable $e) {
    $houses = [];
}

$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['houseName'] ?? 'House');
}

$baseFee = 0.0;
foreach ($feeCategories as $fee) {
    $baseFee += (float) ($fee['amount'] ?? 0);
}
if ($baseFee <= 0) {
    $baseFee = 650.0;
}

$rows = [];
foreach (StudentService::all($isHouseScoped ? $assignedHouseId : null) as $student) {
    $studentId = (string) ($student['id'] ?? $student['uid'] ?? '');
    $name = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
    $houseName = $houseMap[(string) ($student['houseId'] ?? '')] ?? 'Unassigned';
    $paid = 0.0;
    foreach ($paymentRecords as $payment) {
        if ((string) ($payment['studentId'] ?? $payment['student_id'] ?? '') !== $studentId) {
            continue;
        }
        $paid += (float) ($payment['amount'] ?? 0);
    }
    $status = $paid >= $baseFee ? 'paid' : 'outstanding';
    $feeLabel = 'Boarding Fee';
    if (!empty($feeCategories)) {
        $feeLabel = (string) ($feeCategories[0]['name'] ?? 'Boarding Fee');
    }

    $rows[] = [
        'id' => $studentId,
        'student' => $name !== '' ? $name : 'Student',
        'house' => $houseName,
        'fee' => $feeLabel,
        'amount' => $baseFee,
        'status' => $status,
    ];
}

if ($search !== '') {
    $rows = array_values(array_filter($rows, static fn (array $row): bool => str_contains(strtolower((string) $row['student']), strtolower($search)) || str_contains(strtolower((string) $row['id']), strtolower($search))));
}
if ($statusFilter !== 'all') {
    $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === $statusFilter));
}

$students = StudentService::all($isHouseScoped ? $assignedHouseId : null);
$editPaymentId = trim((string) ($_GET['editPaymentId'] ?? ''));
$editPayment = null;
if ($editPaymentId !== '') {
    foreach ($paymentRecords as $row) {
        if ((string) ($row['id'] ?? '') === $editPaymentId) {
            $editPayment = $row;
            break;
        }
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
                <h4 class="mb-1 fw-bold">Student Payments</h4>
                <p class="text-muted mb-0">Record, track, and review student fee payments for each house.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/index/index.php')) ?>" class="btn btn-outline-secondary">Back to Finance</a>
        </div>

<div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="fw-bold mb-0">Payment ledger</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus-circle me-1"></i>Record Payment
                    </button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>" class="row g-3 p-3">
                        <input type="hidden" name="action" value="add">
                        <div class="modal-header px-0 pt-0 border-0">
                            <h5 class="modal-title">Record a payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Student</label>
                            <select name="studentId" class="form-select" required>
                                <option value="">Select student</option>
                                <?php foreach ($students as $student): ?>
                                    <?php $studentId = (string) ($student['id'] ?? $student['uid'] ?? ''); ?>
                                    <?php $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))); ?>
                                    <?php if ($studentId === '') continue; ?>
                                    <option value="<?= e($studentId) ?>"><?= e($studentName !== '' ? $studentName : $studentId) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fee</label>
                            <input type="text" name="feeName" class="form-control" value="Boarding Fee" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" step="0.01" class="form-control" placeholder="650.00" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Method</label>
                            <select name="paymentMethod" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="POS">POS</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="Receipt / transaction no.">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional note about the payment">
                        </div>
                        <div class="col-12 modal-footer px-0 pb-0 border-0 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 mb-3">
                    <input type="hidden" name="route" value="/views/admin/finance/payments/index.php">
                    <div class="col-md-4">
                        <label class="form-label">Search student</label>
                        <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Student name or ID">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="outstanding" <?= $statusFilter === 'outstanding' ? 'selected' : '' ?>>Outstanding</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')) ?>" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Fee</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Statement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['student']) ?></td>
                                    <td><?= e($row['house']) ?></td>
                                    <td><?= e($row['fee']) ?></td>
                                    <td>GHS <?= number_format((float) $row['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Outstanding</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#paymentStatementModal-<?= e((string) $row['id']) ?>">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No payment records match the selected filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    <h5 class="fw-bold mb-3">Payment ledger</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Fee</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actualPaymentRows as $payment): ?>
                                    <tr>
                                        <td><?= e($payment['studentName']) ?></td>
                                        <td><?= e($payment['feeName']) ?></td>
                                        <td>GHS <?= number_format((float) $payment['amount'], 2) ?></td>
                                        <td><?= e($payment['method']) ?></td>
                                        <td><?= e($payment['reference']) ?></td>
                                        <td><?= e(date('d M Y', strtotime($payment['date']))) ?></td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#paymentReceiptModal-<?= e((string) $payment['id']) ?>">View Receipt</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPaymentModal<?= e((string) $payment['id']) ?>">Edit</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePaymentModal<?= e((string) $payment['id']) ?>">Delete</button>
                                            </div>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editPaymentModal<?= e((string) $payment['id']) ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>" class="row g-3 p-3">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="paymentId" value="<?= e((string) $payment['id']) ?>">
                                                    <div class="modal-header px-0 pt-0 border-0">
                                                        <h5 class="modal-title">Edit payment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Student</label>
                                                        <select name="studentId" class="form-select" required>
                                                            <?php foreach ($students as $student): ?>
                                                                <?php $studentId = (string) ($student['id'] ?? $student['uid'] ?? ''); ?>
                                                                <?php $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))); ?>
                                                                <?php if ($studentId === '') continue; ?>
                                                                <option value="<?= e($studentId) ?>" <?= (string) ($payment['studentId'] ?? '') === $studentId ? 'selected' : '' ?>><?= e($studentName !== '' ? $studentName : $studentId) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Fee</label>
                                                        <input type="text" name="feeName" class="form-control" value="<?= e((string) ($payment['feeName'] ?? 'Boarding Fee')) ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Amount</label>
                                                        <input type="number" name="amount" step="0.01" class="form-control" value="<?= e((string) ($payment['amount'] ?? '0')) ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Method</label>
                                                        <select name="paymentMethod" class="form-select">
                                                            <option value="Cash" <?= strtolower((string) ($payment['method'] ?? 'Cash')) === 'cash' ? 'selected' : '' ?>>Cash</option>
                                                            <option value="Mobile Money" <?= strtolower((string) ($payment['method'] ?? 'Cash')) === 'mobile money' ? 'selected' : '' ?>>Mobile Money</option>
                                                            <option value="Bank Transfer" <?= strtolower((string) ($payment['method'] ?? 'Cash')) === 'bank transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                                            <option value="POS" <?= strtolower((string) ($payment['method'] ?? 'Cash')) === 'pos' ? 'selected' : '' ?>>POS</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="paid" <?= strtolower((string) ($payment['status'] ?? 'paid')) === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                            <option value="pending" <?= strtolower((string) ($payment['status'] ?? 'paid')) === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="partial" <?= strtolower((string) ($payment['status'] ?? 'paid')) === 'partial' ? 'selected' : '' ?>>Partial</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Reference</label>
                                                        <input type="text" name="reference" class="form-control" value="<?= e((string) ($payment['reference'] ?? '')) ?>">
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label">Notes</label>
                                                        <input type="text" name="notes" class="form-control" value="<?= e((string) ($payment['notes'] ?? '')) ?>">
                                                    </div>
                                                    <div class="col-12 modal-footer px-0 pb-0 border-0 justify-content-end">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-success">Update</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="deletePaymentModal<?= e((string) $payment['id']) ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-sm modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="paymentId" value="<?= e((string) $payment['id']) ?>">
                                                    <div class="modal-body text-center">
                                                        <i class="bi bi-trash fs-1 text-danger d-block mb-3"></i>
                                                        <h5 class="fw-bold">Delete payment?</h5>
                                                        <p class="mb-0">This will remove the payment for <strong><?= e($payment['studentName']) ?></strong>.</p>
                                                    </div>
                                                    <div class="modal-footer justify-content-center">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($actualPaymentRows)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No payment records have been saved yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($actualPaymentRows as $payment): ?>
            <div class="modal fade" id="paymentReceiptModal-<?= e((string) $payment['id']) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Payment Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e($payment['studentName']) ?></dd><dt class="col-sm-5">Fee</dt><dd class="col-sm-7"><?= e($payment['feeName']) ?></dd><dt class="col-sm-5">Amount</dt><dd class="col-sm-7">GHS <?= number_format((float) $payment['amount'], 2) ?></dd><dt class="col-sm-5">Method</dt><dd class="col-sm-7"><?= e($payment['method']) ?></dd><dt class="col-sm-5">Reference</dt><dd class="col-sm-7"><?= e($payment['reference']) ?></dd><dt class="col-sm-5">Date</dt><dd class="col-sm-7"><?= e(date('d M Y', strtotime($payment['date']))) ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
        <?php endforeach; ?>

        <?php foreach ($rows as $row): ?>
            <div class="modal fade" id="paymentStatementModal-<?= e((string) $row['id']) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Student Payment Statement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e($row['student']) ?></dd><dt class="col-sm-5">House</dt><dd class="col-sm-7"><?= e($row['house']) ?></dd><dt class="col-sm-5">Fee</dt><dd class="col-sm-7"><?= e($row['fee']) ?></dd><dt class="col-sm-5">Amount</dt><dd class="col-sm-7">GHS <?= number_format((float) $row['amount'], 2) ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst($row['status'])) ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
        <?php endforeach; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
