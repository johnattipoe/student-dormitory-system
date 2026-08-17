<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\StudentService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$attendance = AttendanceService::forDate($date, $houseId);
$incidents = (new IncidentService())->byHouse($houseId, true);
$summary = AttendanceService::summary($date, $houseId);

$pageTitle = 'House Master Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">House Reports</h5>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="route" value="/views/house-master/reports/index.php">
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                <button class="btn btn-primary btn-sm">View</button>
            </form>
        </div>
        <?php $reportType = 'house_master'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>

        <div class="card stat-card p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Students</div>
                        <h4 class="mb-0"><?= e(count($students)) ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Attendance Today</div>
                        <h4 class="mb-0"><?= e($summary['total'] ?? 0) ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Open Incidents</div>
                        <h4 class="mb-0"><?= e(count($incidents)) ?></h4>
                    </div>
                </div>
            </div>

            <div class="mt-4 row g-3">
                <div class="col-md-4">
                    <div class="card p-3">
                        <h6>Present</h6>
                        <p class="mb-0 fs-4"><?= e($summary['present'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3">
                        <h6>Absent</h6>
                        <p class="mb-0 fs-4"><?= e($summary['absent'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3">
                        <h6>Late</h6>
                        <p class="mb-0 fs-4"><?= e($summary['late'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
