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

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AttendanceService::update($id, [
        'status' => sanitize($_POST['status'] ?? 'present'),
        'date' => sanitize($_POST['date'] ?? ($entry['date'] ?? date('Y-m-d'))),
    ]);
    flash('success', 'Attendance updated.');
    redirect(url('views/house-master/attendance/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Edit Attendance';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Attendance Record</h4>
                <p class="text-muted mb-0">Modify roll call date or status for this student</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-eye me-1"></i>View Record
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/attendance/history/history.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 680px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Student & Record Information</h6>
            </div>
            <div class="card-body p-4">
                <div class="p-3 bg-light rounded mb-4">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Student</span>
                            <strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($entry['studentId'] ?? '—')) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Bed Allocation</span>
                            <strong><?= e($bed['bedNumber'] ?? 'Not assigned') ?></strong>
                        </div>
                    </div>
                </div>

                <form method="POST" action="<?= url('views/house-master/attendance/edit/edit.php?id=' . urlencode($id)) ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Attendance Date <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="date" class="form-control" value="<?= e($entry['date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Attendance Status <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select" required>
                                    <option value="present" <?= ($entry['status'] ?? '') === 'present' ? 'selected' : '' ?>>Present</option>
                                    <option value="absent" <?= ($entry['status'] ?? '') === 'absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="late" <?= ($entry['status'] ?? '') === 'late' ? 'selected' : '' ?>>Late</option>
                                    <option value="excused" <?= ($entry['status'] ?? '') === 'excused' ? 'selected' : '' ?>>Excused</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode($id)) ?>">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>