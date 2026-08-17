<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\VisitorService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$students = StudentService::all();
$attendance = AttendanceService::forDate($date);
$visitors = (new VisitorService())->all();
$incidents = array_values(array_filter((new IncidentService())->all(), fn($incident) => ($incident['status'] ?? 'open') === 'open'));
$summary = AttendanceService::summary($date);

$pageTitle = 'Houseparent Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
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
            <h5 class="mb-0">Houseparent Reports</h5>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="route" value="/views/houseparent/reports/index.php">
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                <button class="btn btn-primary btn-sm">View</button>
            </form>
        </div>
        <?php $reportType = 'houseparent'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>

        <div class="card stat-card p-4">
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="text-muted small">Students</div>
                        <h4 class="mb-0"><?= e(count($students)) ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="text-muted small">Present</div>
                        <h4 class="mb-0"><?= e($summary['present'] ?? 0) ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="text-muted small">Absent</div>
                        <h4 class="mb-0"><?= e($summary['absent'] ?? 0) ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="text-muted small">Visitors</div>
                        <h4 class="mb-0"><?= e(count($visitors)) ?></h4>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h6>Attendance Summary</h6>
                        <p class="mb-0 text-muted">Late: <?= e($summary['late'] ?? 0) ?> · Excused: <?= e($summary['excused'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h6>Open Incidents</h6>
                        <p class="mb-0 text-muted"><?= e(count($incidents)) ?> active incident(s) in this house</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
