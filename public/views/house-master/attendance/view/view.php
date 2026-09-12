<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
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

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\StudentService;
use App\Services\UserService;

$id = sanitize($_GET['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$entry = null;
foreach (AttendanceService::byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $entry = $record;
        break;
    }
}

if (!$entry) {
    flash('error', 'Attendance record not found.');
    redirect(url('views/house-master/attendance/history/history.php'));
}

$student = StudentService::find((string) ($entry['studentId'] ?? ''));
$bed = null;
foreach (BedService::all() as $candidateBed) {
    if ((string) ($candidateBed['studentId'] ?? '') === (string) ($entry['studentId'] ?? '')) {
        $bed = $candidateBed;
        break;
    }
}

$rawMarkedBy = (string) ($entry['markedBy'] ?? '');
$markedByName = trim((string) ($entry['markedByName'] ?? ''));

if ($markedByName === '' || str_starts_with($markedByName, 'Staff/User')) {
    if ($rawMarkedBy !== '') {
        $markedByUser = (new UserService())->find($rawMarkedBy);
        if ($markedByUser) {
            $name = trim((string) ($markedByUser['name'] ?? ''));
            if ($name === '') {
                $name = trim(($markedByUser['firstName'] ?? '') . ' ' . ($markedByUser['lastName'] ?? ''));
            }
            if ($name === '') {
                $name = $markedByUser['displayName'] ?? $markedByUser['username'] ?? $markedByUser['email'] ?? '';
            }
            if ($name !== '') {
                $role = !empty($markedByUser['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $markedByUser['role'])) . ')' : '';
                $markedByName = $name . $role;
            }
        }
        if ($markedByName === '') {
            $markedByStudent = StudentService::find($rawMarkedBy);
            if ($markedByStudent) {
                $sName = trim(($markedByStudent['firstName'] ?? '') . ' ' . ($markedByStudent['lastName'] ?? ''));
                if ($sName !== '') {
                    $markedByName = $sName . ' (Student)';
                }
            }
        }
    }
}
if ($markedByName === '') {
    $markedByName = $rawMarkedBy !== '' ? $rawMarkedBy : '—';
}

$status = $entry['status'] ?? 'present';
$statusBadge = match($status) {
    'present' => 'bg-success text-white',
    'absent' => 'bg-danger text-white',
    'late' => 'bg-warning text-dark',
    'excused' => 'bg-info text-dark',
    default => 'bg-secondary text-white'
};

$pageTitle = 'Attendance Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar-check text-primary me-2"></i>Attendance Record Details</h4>
                <p class="text-muted mb-0">Review roll call verification and student status entry</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/attendance/history/history.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <a class="btn btn-warning btn-sm" href="<?= url('views/house-master/attendance/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= url('views/house-master/attendance/delete/delete.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-trash me-1"></i>Delete
                </a>
            </div>
        </div>

        <!-- Detail Card -->
        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 760px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-check me-2 text-primary"></i>Roll Call Entry</h6>
                <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($status)) ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Student Name</span>
                        <strong class="fs-6"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($entry['studentId'] ?? '—')) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Admission Number</span>
                        <strong class="fs-6 font-monospace"><?= e($student['admissionNo'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Bed Allocation</span>
                        <strong><?= e($bed['bedNumber'] ?? 'Not assigned') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Roll Call Date</span>
                        <strong><?= e($entry['date'] ?? '') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Status Recorded</span>
                        <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($status)) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Recorded By</span>
                        <strong><?= e($markedByName) ?></strong>
                    </div>
                </div>

                <?php if (!empty($entry['notes'])): ?>
                    <div class="pt-3 border-top">
                        <span class="text-muted small d-block mb-1 fw-semibold">Notes / Reason</span>
                        <p class="text-muted small mb-0"><?= e($entry['notes']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/attendance/history/history.php') ?>">
                        Back to History
                    </a>
                    <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/attendance/edit/edit.php?id=' . urlencode($id)) ?>">
                        <i class="bi bi-pencil me-1"></i>Edit Record
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>