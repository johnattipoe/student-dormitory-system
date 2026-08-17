<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\HouseService;
use App\Services\StudentService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$attendance = AttendanceService::forDate($date);
$students = StudentService::all();
$houses = HouseService::all();
$summary = AttendanceService::summary($date);
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = $house['name'] ?? 'House';
}

$pageTitle = 'Senior Houseparent Attendance';
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">All House Attendance</h5>
                <small class="text-muted">Monitoring attendance submitted by the house master across all houses.</small>
            </div>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="route" value="/views/houseparent/attendance/index.php">
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                <button class="btn btn-primary btn-sm">View</button>
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

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>House</th>
                        <th>Status</th>
                        <th>Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No attendance records found for this date.</td>
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
                                <td><?= e($houseMap[(string) ($record['houseId'] ?? '')] ?? ($student['houseId'] ?? ($record['houseId'] ?? '—'))) ?></td>
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