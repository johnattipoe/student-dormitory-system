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
use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\VisitorService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$attendance = AttendanceService::forDate($date, $houseId);
$incidents = (new IncidentService())->byHouse($houseId, true);
$summary = AttendanceService::summary($date, $houseId);
$roomStats = RoomService::occupancyStats($houseId);
$visitorRecords = (new VisitorService())->byHouse($houseId);
$visitorCount = count($visitorRecords);
$visitorInside = count(array_filter($visitorRecords, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));
$visitorCheckedOut = count(array_filter($visitorRecords, fn($visitor) => ($visitor['status'] ?? '') === 'checked_out'));
$studentStatusCounts = [];
foreach ($students as $student) {
    $studentStatus = (string) ($student['status'] ?? 'unknown');
    $studentStatusCounts[$studentStatus] = ($studentStatusCounts[$studentStatus] ?? 0) + 1;
}
$priorityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
foreach ($incidents as $incident) {
    $priority = strtolower((string) ($incident['priority'] ?? $incident['severity'] ?? 'low'));
    if (isset($priorityCounts[$priority])) {
        $priorityCounts[$priority]++;
    }
}
$attendanceRate = count($students) > 0 ? round((($summary['present'] ?? 0) / count($students)) * 100) : 0;

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
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div><h5 class="mb-1">House Reports</h5><p class="text-muted mb-0">Operational snapshot for <?= e($date) ?>.</p></div>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="route" value="/views/house-master/reports/index.php">
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                <button class="btn btn-primary btn-sm">View</button>
            </form>
        </div>
        <?php $reportType = 'house_master'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>
        <div class="mb-3"><a class="btn btn-success btn-sm" href="<?= url('views/house-master/reports/export.php?type=house_master&date=' . urlencode($date)) ?>"><i class="bi bi-filetype-csv"></i> Download summary CSV</a></div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/attendance.php?date=' . urlencode($date)) ?>">Attendance detail</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/occupancy.php') ?>">Occupancy detail</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/visitors.php') ?>">Visitor detail</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/incidents.php') ?>">Incident detail</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Attendance rate</small><div class="d-flex align-items-end gap-2"><strong class="fs-2"><?= e((string) $attendanceRate) ?>%</strong><span class="text-muted small mb-2">present</span></div><div class="progress mt-2" style="height:8px"><div class="progress-bar bg-success" style="width:<?= e((string) min(100, $attendanceRate)) ?>%"></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Available spaces</small><strong class="fs-2"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></strong><span class="small text-muted">of <?= e((string) ($roomStats['capacity'] ?? 0)) ?> total capacity</span></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Visitors inside</small><strong class="fs-2 text-success"><?= e((string) $visitorInside) ?></strong><span class="small text-muted"><?= e((string) $visitorCheckedOut) ?> checked out</span></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 h-100"><small class="text-muted">High-priority incidents</small><strong class="fs-2 text-danger"><?= e((string) $priorityCounts['high']) ?></strong><span class="small text-muted"><?= e((string) count($incidents)) ?> open incidents</span></div></div>
        </div>

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
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Visitors on record</div>
                        <h4 class="mb-0"><?= e($visitorCount) ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Room occupancy</div>
                        <h4 class="mb-0"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</h4>
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
            <div class="mt-4 d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="<?= url('views/house-master/attendance/history.php') ?>">Open attendance history</a>
                <a class="btn btn-outline-primary" href="<?= url('views/house-master/rooms/index.php') ?>">Review room allocation</a>
                <a class="btn btn-outline-primary" href="<?= url('views/house-master/incidents/index.php') ?>">Review incidents</a>
                <a class="btn btn-outline-primary" href="<?= url('views/house-master/visitors/index.php') ?>">Review visitors</a>
                <a class="btn btn-outline-primary" href="<?= url('views/house-master/students/index.php') ?>">Review students</a>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Attendance distribution</h6><span class="badge bg-light text-dark"><?= e($date) ?></span></div>
                    <?php foreach (['present' => ['Present', 'success'], 'absent' => ['Absent', 'danger'], 'late' => ['Late', 'warning'], 'excused' => ['Excused', 'info']] as $key => [$label, $color]): ?>
                        <?php $value = (int) ($summary[$key] ?? 0); $width = ($summary['total'] ?? 0) > 0 ? round(($value / $summary['total']) * 100) : 0; ?>
                        <div class="mb-3"><div class="d-flex justify-content-between small mb-1"><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></div><div class="progress" style="height:8px"><div class="progress-bar bg-<?= e($color) ?>" style="width:<?= e((string) $width) ?>%"></div></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">House composition</h6><a class="small" href="<?= url('views/house-master/students/index.php') ?>">Open directory</a></div>
                    <?php foreach ($studentStatusCounts as $studentStatus => $count): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e(ucfirst($studentStatus)) ?> students</span><strong><?= e((string) $count) ?></strong></div><?php endforeach; ?>
                    <div class="d-flex justify-content-between py-2"><span>Total room capacity</span><strong><?= e((string) ($roomStats['capacity'] ?? 0)) ?></strong></div><div class="d-flex justify-content-between py-2"><span>Current occupancy</span><strong><?= e((string) ($roomStats['occupied'] ?? 0)) ?> (<?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%)</strong></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6"><div class="card stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Incident priorities</h6><a class="small" href="<?= url('views/house-master/incidents/index.php') ?>">Open incident log</a></div><?php foreach ($priorityCounts as $priority => $count): ?><div class="d-flex justify-content-between py-2 border-bottom"><span><?= e(ucfirst($priority)) ?> priority</span><strong><?= e((string) $count) ?></strong></div><?php endforeach; ?></div></div>
            <div class="col-lg-6"><div class="card stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Visitor activity</h6><a class="small" href="<?= url('views/house-master/visitors/index.php') ?>">Open visitor log</a></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Total visitor records</span><strong><?= e((string) $visitorCount) ?></strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Currently inside</span><strong class="text-success"><?= e((string) $visitorInside) ?></strong></div><div class="d-flex justify-content-between py-2"><span>Checked out</span><strong><?= e((string) $visitorCheckedOut) ?></strong></div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
