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
$incidents = (new IncidentService())->byHouse($houseId, true);
$summary = AttendanceService::summary($date, $houseId);
$roomStats = RoomService::occupancyStats($houseId);
$visitorRecords = (new VisitorService())->byHouse($houseId);
$visitorCount = count($visitorRecords);
$visitorInside = count(array_filter($visitorRecords, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));
$visitorCheckedOut = count(array_filter($visitorRecords, fn($visitor) => ($visitor['status'] ?? '') === 'checked_out'));

// Medical records for this house
$allMedical = FirebaseService::getInstance()->getCollection('medical_records', [], 500);
$medicalRecords = array_values(array_filter($allMedical, fn($r) => isset($studentIds[(string)($r['studentId'] ?? '')])));
$emergencyCount = count(array_filter($medicalRecords, fn($r) => strtolower((string)($r['severity'] ?? '')) === 'emergency'));

$studentStatusCounts = [];
foreach ($students as $student) {
    $studentStatus = (string) ($student['status'] ?? 'active');
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

$pageTitle = 'House Reports & Analytics';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Health Reports', 'href' => url('views/house-master/health-reports/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bar-chart-fill text-success me-2"></i>Reports & Analytics</h4>
                <p class="text-muted mb-0"><?= e($houseName) ?> — Daily snapshot for <?= e(date('F d, Y', strtotime($date))) ?></p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                </form>
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/reports/export/export.php?type=house_master&date=' . urlencode($date)) ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </a>
            </div>
        </div>

        <!-- Quick Report Links -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/reports/attendance/attendance.php?date=' . urlencode($date)) ?>">
                        <i class="bi bi-calendar-check me-1"></i>Attendance Breakdown
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/reports/occupancy/occupancy.php') ?>">
                        <i class="bi bi-door-closed me-1"></i>Room & Bed Occupancy
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/reports/visitors/visitors.php') ?>">
                        <i class="bi bi-people me-1"></i>Visitor Activity
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/reports/incidents/incidents.php') ?>">
                        <i class="bi bi-flag me-1"></i>Incidents & Discipline
                    </a>
                </div>
            </div>
        </div>

        <!-- Top KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Attendance Rate</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $attendanceRate) ?>%</h3>
                            <div class="progress mt-1" style="height:6px;width:100px">
                                <div class="progress-bar bg-success" style="width:<?= e((string) min(100, $attendanceRate)) ?>%"></div>
                            </div>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available Beds</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></h3>
                            <span class="small text-muted">of <?= e((string) ($roomStats['capacity'] ?? 0)) ?> (<?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>% occ.)</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Visitors</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $visitorInside) ?></h3>
                            <span class="small text-muted"><?= e((string) $visitorCheckedOut) ?> checked out</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-person-walking fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">High Priority</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) ($priorityCounts['high'] + $emergencyCount)) ?></h3>
                            <span class="small text-muted"><?= e((string) $priorityCounts['high']) ?> incidents, <?= e((string) $emergencyCount) ?> medical</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>Attendance Distribution (<?= e($date) ?>)</h6>
                        <a class="small text-decoration-none" href="<?= url('views/house-master/attendance/index/index.php') ?>">Roll Call</a>
                    </div>
                    <div class="card-body p-4">
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
            </div>
            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>House Demographics & Occupancy</h6>
                        <a class="small text-decoration-none" href="<?= url('views/house-master/students/index/index.php') ?>">Student Directory</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Total Assigned Students</span>
                                <strong><?= e((string) count($students)) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Total Dormitory Rooms</span>
                                <strong><?= e((string) ($roomStats['rooms'] ?? 0)) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Bed Capacity</span>
                                <strong><?= e((string) ($roomStats['capacity'] ?? 0)) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Allocated Beds</span>
                                <strong class="text-primary"><?= e((string) ($roomStats['occupied'] ?? 0)) ?> (<?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%)</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Detail Cards -->
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Incident Log</h6>
                        <a class="small text-decoration-none" href="<?= url('views/house-master/incidents/index/index.php') ?>">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Total Incidents</span>
                                <strong><?= e((string) count($incidents)) ?></strong>
                            </li>
                            <?php foreach ($priorityCounts as $priority => $count): ?>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span><?= e(ucfirst($priority)) ?> Priority</span>
                                    <strong class="<?= $priority === 'high' ? 'text-danger' : '' ?>"><?= e((string) $count) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-walking me-2"></i>Visitor Activity</h6>
                        <a class="small text-decoration-none" href="<?= url('views/house-master/visitors/index/index.php') ?>">View Log</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Total Logged Visits</span>
                                <strong><?= e((string) $visitorCount) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Currently Inside</span>
                                <strong class="text-success"><?= e((string) $visitorInside) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Checked Out</span>
                                <strong><?= e((string) $visitorCheckedOut) ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-heart-pulse me-2"></i>Medical Records</h6>
                        <a class="small text-decoration-none" href="<?= url('views/house-master/health-reports/index.php') ?>">Health Reports</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Total Health Reports</span>
                                <strong><?= e((string) count($medicalRecords)) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Emergency Cases</span>
                                <strong class="text-danger"><?= e((string) $emergencyCount) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-3">
                                <span>Standard / Observation</span>
                                <strong><?= e((string) (count($medicalRecords) - $emergencyCount)) ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
