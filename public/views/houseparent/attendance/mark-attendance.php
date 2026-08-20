 <!-- #region --><?php
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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\AttendanceService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = sanitize($_POST['studentId'] ?? '');
    $studentIds = $_POST['studentIds'] ?? [];
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $status = sanitize($_POST['status'] ?? 'present');
    $notes = sanitize($_POST['notes'] ?? '');
    
    $markedIds = [];
    $errors = [];
    
    // Bulk marking
    if (!empty($studentIds) && is_array($studentIds)) {
        foreach ($studentIds as $sId) {
            $sId = sanitize($sId);
            if ($sId) {
                try {
                    AttendanceService::mark($sId, $status, $date, current_user()['houseId'], current_user()['uid']);
                    $markedIds[] = $sId;
                } catch (Exception $e) {
                    $errors[] = "Failed for " . $sId . ": " . $e->getMessage();
                }
            }
        }
        if (!empty($markedIds)) {
            flash('success', 'Attendance marked for ' . count($markedIds) . ' student(s)');
        }
        if (!empty($errors)) {
            flash('warning', implode('; ', $errors));
        }
        redirect(base_url('index.php?route=/views/houseparent/attendance/index.php'));
    }
    
    // Single marking
    if ($studentId && $date) {
        try {
            $result = AttendanceService::mark($studentId, $status, $date, current_user()['houseId'], current_user()['uid']);
            flash('success', $result['message'] ?? 'Attendance marked successfully');
            redirect(base_url('index.php?route=/views/houseparent/attendance/index.php'));
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
    }
}

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($studentSearch !== '') {
    $students = array_values(array_filter($students, fn($student) => str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['roomId'] ?? ''))), $studentSearch)));
}

$pageTitle = 'Mark Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:900px;">
            <h5 class="mb-3">Mark Attendance</h5>
            <div class="alert alert-light border d-flex justify-content-between align-items-center"><span><strong><?= e((string) count($students)) ?></strong> students available.</span><a class="btn btn-sm btn-outline-primary" href="<?= url('views/houseparent/attendance/history.php') ?>">View history</a></div>
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single-mode" type="button">Single Student</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk-mode" type="button">Bulk Mark</button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Single Student Mode -->
                <div class="tab-pane fade show active" id="single-mode" role="tabpanel">
                    <form method="POST" action="<?= url('views/houseparent/attendance/mark-attendance.php') ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Student</label>
                                <select name="studentId" class="form-select">
                                    <option value="">-- Select a student --</option>
                                    <?php foreach ($students as $s): ?>
                                        <option value="<?= e((string) ($s['id'] ?? '')) ?>">
                                            <?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?> (<?= e($s['admissionNo'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="excused">Excused</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes (optional)</label>
                                <input type="text" name="notes" class="form-control" placeholder="e.g., Medical appointment">
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">Submit</button>
                            <a href="<?= url('views/houseparent/attendance/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <!-- Bulk Mode -->
                <div class="tab-pane fade" id="bulk-mode" role="tabpanel">
                    <form method="POST" action="<?= url('views/houseparent/attendance/mark-attendance.php') ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="excused">Excused</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes (optional)</label>
                                <input type="text" name="notes" class="form-control" placeholder="e.g., After sports event">
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <small>Select students below to mark their attendance with the status above.</small>
                        </div>

                        <form method="GET" class="row g-2 mb-3"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Filter students by name, admission number, or room" value="<?= e($studentSearch) ?>"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm">Filter roster</button></div></form>

                        <div class="table-responsive mb-3" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllStudents" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Admission No.</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="studentIds[]" value="<?= e((string) ($s['id'] ?? '')) ?>" class="form-check-input student-checkbox">
                                            </td>
                                            <td><?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?></td>
                                            <td><?= e($s['admissionNo'] ?? '') ?></td>
                                            <td><?= e($s['roomId'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">Mark Attendance</button>
                            <a href="<?= url('views/houseparent/attendance/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Select/deselect all checkboxes
            document.getElementById('selectAllStudents')?.addEventListener('change', function() {
                document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        </script>
    </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
