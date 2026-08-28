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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\VisitorService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$studentIds = array_fill_keys(array_filter(array_map(fn($s) => (string)($s['id'] ?? ''), $students)), true);

$attendance = AttendanceService::forDate($date, $houseId);
$incidents = (new IncidentService())->byHouse($houseId);
$summary = AttendanceService::summary($date, $houseId);
$roomStats = RoomService::occupancyStats($houseId);
$visitors = (new VisitorService())->byHouse($houseId);
$visitorInside = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'inside'));
$visitorCheckedOut = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'checked_out'));

// Medical records for this house
$allMedical = FirebaseService::getInstance()->getCollection('medical_records', [], 500);
$medicalRecords = array_values(array_filter($allMedical, fn($r) => isset($studentIds[(string)($r['studentId'] ?? '')])));
$emergencyCount = count(array_filter($medicalRecords, fn($r) => strtolower((string)($r['severity'] ?? '')) === 'emergency'));

$priorityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
foreach ($incidents as $incident) {
    $priority = strtolower((string) ($incident['priority'] ?? $incident['severity'] ?? 'low'));
    if (isset($priorityCounts[$priority])) {
        $priorityCounts[$priority]++;
    }
}
$attendanceRate = count($students) > 0 ? round((($summary['present'] ?? 0) / count($students)) * 100) : 0;

$pageTitle = 'Senior Houseparent Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/senior-houseparent/reports/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">House Reports & Analytics (<?= e($houseName) ?>)</h5>
                <p class="text-muted mb-0">Operational summary and dormitory performance for <?= e(date('F d, Y', strtotime($date))) ?>.</p>
            </div>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                <button class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/reports/attendance/attendance.php?date=' . urlencode($date)) ?>">
                <i class="bi bi-calendar-check me-1"></i> Attendance Breakdown
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/reports/occupancy/occupancy.php') ?>">
                <i class="bi bi-door-closed me-1"></i> Room & Bed Occupancy
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/reports/visitors/visitors.php') ?>">
                <i class="bi bi-people me-1"></i> Visitor Activity
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/reports/incidents/incidents.php') ?>">
                <i class="bi bi-shield-exclamation me-1"></i> Incidents & Discipline
            </a>
            <a class="btn btn-outline-success btn-sm" href="<?= url('views/senior-houseparent/reports/export/export.php?type=senior_houseparent&date=' . urlencode($date)) ?>">
                <i class="bi bi-filetype-csv me-1"></i> Export Summary CSV
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Attendance Rate</small>
                    <div class="d-flex align-items-end gap-2 my-1">
                        <strong class="fs-2"><?= e((string) $attendanceRate) ?>%</strong>
                        <span class="text-muted small mb-2">present</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-success" style="width:<?= e((string) min(100, $attendanceRate)) ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Available Beds</small>
                    <div class="fs-2 fw-bold text-success my-1"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></div>
                    <span class="small text-muted">of <?= e((string) ($roomStats['capacity'] ?? 0)) ?> total capacity (<?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>% occupied)</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Active Visitors</small>
                    <div class="fs-2 fw-bold text-primary my-1"><?= e((string) $visitorInside) ?> inside</div>
                    <span class="small text-muted"><?= e((string) $visitorCheckedOut) ?> checked out today</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">High Priority / Urgent</small>
                    <div class="fs-2 fw-bold text-danger my-1"><?= e((string) ($priorityCounts['high'] + $emergencyCount)) ?></div>
                    <span class="small text-muted"><?= e((string) $priorityCounts['high']) ?> incidents, <?= e((string) $emergencyCount) ?> medical alerts</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Attendance Distribution (<?= e($date) ?>)</h6>
                        <a class="small text-decoration-none" href="<?= url('views/senior-houseparent/attendance/index/index.php') ?>">Roll Call</a>
                    </div>
                    <?php foreach (['present' => ['Present', 'success'], 'absent' => ['Absent', 'danger'], 'late' => ['Late', 'warning'], 'excused' => ['Excused', 'info']] as $key => [$label, $color]): ?>
                        <?php 
                            $value = (int) ($summary[$key] ?? 0); 
                            $width = ($summary['total'] ?? 0) > 0 ? round(($value / $summary['total']) * 100) : 0; 
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= e($label) ?></span>
                                <strong><?= e((string) $value) ?> (<?= e((string)$width) ?>%)</strong>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-<?= e($color) ?>" style="width:<?= e((string) $width) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">House Demographics & Bed Allocations</h6>
                        <a class="small text-decoration-none" href="<?= url('views/senior-houseparent/students/index/index.php') ?>">Student Directory</a>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Total Assigned Students</span>
                        <strong><?= e((string) count($students)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Total Rooms</span>
                        <strong><?= e((string) ($roomStats['rooms'] ?? 0)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Bed Capacity</span>
                        <strong><?= e((string) ($roomStats['capacity'] ?? 0)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Allocated Beds</span>
                        <strong class="text-primary"><?= e((string) ($roomStats['occupied'] ?? 0)) ?> (<?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%)</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Incident Log</h6>
                        <a class="small text-decoration-none" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">View All</a>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Total Incidents</span>
                        <strong><?= e((string) count($incidents)) ?></strong>
                    </div>
                    <?php foreach ($priorityCounts as $priority => $count): ?>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span><?= e(ucfirst($priority)) ?> Priority</span>
                            <strong class="<?= $priority === 'high' ? 'text-danger' : '' ?>"><?= e((string) $count) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Visitor Activity</h6>
                        <a class="small text-decoration-none" href="<?= url('views/senior-houseparent/visitors/index/index.php') ?>">View Log</a>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Total Logged Visits</span>
                        <strong><?= e((string) count($visitors)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Currently Inside</span>
                        <strong class="text-success"><?= e((string) $visitorInside) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Checked Out</span>
                        <strong><?= e((string) $visitorCheckedOut) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Clinic / Health Records</h6>
                        <span class="badge bg-primary-subtle text-primary border">Clinic</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Total Health Reports</span>
                        <strong><?= e((string) count($medicalRecords)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Emergency Cases</span>
                        <strong class="text-danger"><?= e((string) $emergencyCount) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Standard / Observation</span>
                        <strong><?= e((string) (count($medicalRecords) - $emergencyCount)) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
