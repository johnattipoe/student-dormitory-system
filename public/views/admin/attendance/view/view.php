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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
$entry = null;
foreach (AttendanceService::all() as $record) {
    if (($record['id'] ?? '') === $id) {
        $entry = $record;
        break;
    }
}

if (!$entry) {
    flash('error', 'Attendance record not found.');
    redirect(url('views/admin/attendance/index/index.php'));
}

$student = StudentService::find((string) ($entry['studentId'] ?? ''));
$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($entry['studentId'] ?? '—');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

$bed = null;
foreach (BedService::all() as $candidate) {
    if ((string) ($candidate['studentId'] ?? '') === (string) ($entry['studentId'] ?? '')) {
        $bed = $candidate;
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

$pageTitle = 'Attendance Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 650px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Attendance Details</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/attendance/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Student</dt>
                <dd class="col-sm-8"><strong><?= e($studentName) ?></strong></dd>

                <dt class="col-sm-4">Bed</dt>
                <dd class="col-sm-8"><?= e($bed['bedNumber'] ?? '—') ?></dd>

                <dt class="col-sm-4">Date</dt>
                <dd class="col-sm-8"><?= e($entry['date'] ?? '') ?></dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($entry['status'] ?? '') === 'present' ? 'success' : (($entry['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>">
                        <?= e(ucfirst($entry['status'] ?? 'present')) ?>
                    </span>
                </dd>

                <dt class="col-sm-4">Marked By</dt>
                <dd class="col-sm-8"><?= e($markedByName) ?></dd>
            </dl>
            <div class="mt-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminEditAttendanceModal"><i class="bi bi-pencil me-1"></i> Edit Record</button>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/admin/attendance/index/index.php') ?>">Back to list</a>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="adminEditAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/attendance/edit/edit.php?id=' . urlencode($id)) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Attendance</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Student</label><input class="form-control" value="<?= e($studentName) ?>" disabled></div>
                <div class="mb-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($entry['date'] ?? date('Y-m-d')) ?>" required></div>
                <label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['present', 'absent', 'late', 'excused'] as $attendanceStatus): ?><option value="<?= e($attendanceStatus) ?>" <?= ($entry['status'] ?? 'present') === $attendanceStatus ? 'selected' : '' ?>><?= ucfirst($attendanceStatus) ?></option><?php endforeach; ?></select>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
