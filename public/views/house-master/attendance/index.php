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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\StudentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'present'),
        'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
        'houseId' => current_user()['houseId'] ?? null,
        'markedBy' => current_user()['uid'] ?? current_user()['id'] ?? 'house-master',
    ];

    $result = AttendanceService::mark($data);
    flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Attendance saved.');
    redirect(base_url('index.php?route=/views/house-master/attendance/index.php'));
}

$pageTitle = 'House Master Attendance';
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$searchQuery = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$students = StudentService::all(current_user()['houseId'] ?? null);
$attendance = AttendanceService::forDate($date, current_user()['houseId'] ?? null);

// Apply filters
if (!empty($searchQuery)) {
    $attendance = array_filter($attendance, function($record) use ($searchQuery, $students) {
        $student = current(array_filter($students, fn($s) => ((string) ($s['id'] ?? '')) === ((string) ($record['studentId'] ?? ''))));
        $name = ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '');
        $admNo = $student['admissionNo'] ?? '';
        return stripos($name, $searchQuery) !== false || stripos($admNo, $searchQuery) !== false;
    });
}

if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($record) => ($record['status'] ?? 'present') === $statusFilter);
}

$summary = AttendanceService::summary($date, current_user()['houseId'] ?? null);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="mb-0">Attendance Overview</h5>
                    <small class="text-muted">Record and review daily attendance for your assigned house.</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('views/house-master/attendance/mark-attendance.php') ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-square"></i> Bulk Mark
                    </a>
                    <a href="<?= url('views/house-master/attendance/history.php') ?>" class="btn btn-outline-secondary btn-sm">History</a>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/house-master/attendance/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Search Student</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or admission no." value="<?= e($searchQuery) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    <a href="<?= url('views/house-master/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Present</div>
                    <div class="fs-2 fw-bold"><?= e($summary['present'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Absent</div>
                    <div class="fs-2 fw-bold"><?= e($summary['absent'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Late</div>
                    <div class="fs-2 fw-bold"><?= e($summary['late'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4 mb-4">
            <h6 class="mb-3">Quick Mark Attendance</h6>
            <form method="POST" action="<?= url('views/house-master/attendance/index.php') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($attendance) ?></strong> record(s)
                <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No attendance records matching your filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance as $record): ?>
                            <?php
                            $student = null;
                            foreach ($students as $s) {
                                if (($s['id'] ?? '') === ($record['studentId'] ?? '')) {
                                    $student = $s;
                                    break;
                                }
                            }
                            ?>
                            <tr>
                                <td><?= e($record['date'] ?? '-') ?></td>
                                <td><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?: e($record['studentId'] ?? '-') ?></td>
                                <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                <td><?= e($record['markedBy'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
