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

$pageTitle = 'Student Payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    if ($action === 'delete') {
        $paymentId = trim((string) ($_POST['paymentId'] ?? ''));
        if ($paymentId !== '') {
            try {
                FirebaseService::getInstance()->deleteDocument(COL_FINANCE_PAYMENTS, $paymentId);
                flash('success', 'Payment deleted successfully.');
            } catch (Throwable $e) {
                flash('error', 'Unable to delete payment: ' . $e->getMessage());
            }
        }
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')));
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
            redirect(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')));
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
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php')));
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
foreach (StudentService::all() as $student) {
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

$students = StudentService::all();
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

        <?php if ($editPayment): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Edit payment</h5>
                    <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>" class="row g-3">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="paymentId" value="<?= e((string) ($editPayment['id'] ?? '')) ?>">
                        <div class="col-md-3">
                            <label class="form-label">Student</label>
                            <select name="studentId" class="form-select" required>
                                <?php foreach ($students as $student): ?>
                                    <?php $studentId = (string) ($student['id'] ?? $student['uid'] ?? ''); ?>
                                    <?php $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))); ?>
                                    <?php if ($studentId === '') continue; ?>
                                    <option value="<?= e($studentId) ?>" <?= (string) ($editPayment['studentId'] ?? '') === $studentId ? 'selected' : '' ?>><?= e($studentName !== '' ? $studentName : $studentId) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fee</label>
                            <input type="text" name="feeName" class="form-control" value="<?= e((string) ($editPayment['feeName'] ?? 'Boarding Fee')) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" step="0.01" class="form-control" value="<?= e((string) ($editPayment['amount'] ?? '0')) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Method</label>
                            <select name="paymentMethod" class="form-select">
                                <option value="Cash" <?= strtolower((string) ($editPayment['paymentMethod'] ?? 'Cash')) === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Mobile Money" <?= strtolower((string) ($editPayment['paymentMethod'] ?? 'Cash')) === 'mobile money' ? 'selected' : '' ?>>Mobile Money</option>
                                <option value="Bank Transfer" <?= strtolower((string) ($editPayment['paymentMethod'] ?? 'Cash')) === 'bank transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                <option value="POS" <?= strtolower((string) ($editPayment['paymentMethod'] ?? 'Cash')) === 'pos' ? 'selected' : '' ?>>POS</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="paid" <?= strtolower((string) ($editPayment['status'] ?? 'paid')) === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="pending" <?= strtolower((string) ($editPayment['status'] ?? 'paid')) === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="partial" <?= strtolower((string) ($editPayment['status'] ?? 'paid')) === 'partial' ? 'selected' : '' ?>>Partial</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Update</button>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" value="<?= e((string) ($editPayment['reference'] ?? '')) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" value="<?= e((string) ($editPayment['notes'] ?? '')) ?>">
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Record a payment</h5>
                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>" class="row g-3">
                    <div class="col-md-3">
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
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Save</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="Receipt / transaction no.">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note about the payment">
                    </div>
                </form>
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
                                        <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/statement/index.php') . '&studentId=' . urlencode((string) $row['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
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
                                                <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/receipt/index.php') . '&paymentId=' . urlencode($payment['id'])) ?>" class="btn btn-sm btn-outline-primary">View Receipt</a>
                                                <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php') . '&editPaymentId=' . urlencode($payment['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/payments/index.php'))) ?>" class="d-inline" onsubmit="return confirm('Delete this payment record?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="paymentId" value="<?= e((string) $payment['id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
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
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
