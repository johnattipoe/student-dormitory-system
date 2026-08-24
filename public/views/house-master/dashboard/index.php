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
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$assignedHouse = $houseId ? HouseService::find((string) $houseId) : null;
$assignedHouseName = $assignedHouse['name'] ?? 'Not assigned';
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$todayAttendance = AttendanceService::todayByHouse($houseId);
$todayVisitors = (new VisitorService())->todayByHouse($houseId);
$openIncidents = (new IncidentService())->byHouse($houseId, true);
$stats = [
    'students' => count($students),
    'rooms' => RoomService::count($houseId),
    'attendance' => count($todayAttendance),
    'visitors' => count($todayVisitors),
    'incidents' => count($openIncidents),
];
$attendanceSummary = AttendanceService::summary(date('Y-m-d'), $houseId);
$roomStats = RoomService::occupancyStats($houseId);

$pageTitle = 'House Master Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
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
        <div class="mb-4">
            <h5 class="mb-1">Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
            <p class="text-muted mb-0">Assigned house: <strong><?= e($assignedHouseName) ?></strong></p>
        </div>

        <div class="row g-3">
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Students</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['students']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Rooms</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['rooms']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Today's Attendance</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['attendance']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Today's Visitors</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['visitors']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Open Incidents</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['incidents']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Present today</small><strong class="fs-3 text-success"><?= e((string) ($attendanceSummary['present'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Absent today</small><strong class="fs-3 text-danger"><?= e((string) ($attendanceSummary['absent'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Vacant spaces</small><strong class="fs-3"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupancy rate</small><strong class="fs-3"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</strong></div></div>
        </div>

        <br>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today's Attendance</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (AttendanceService::todayByHouse($houseId) as $attendance):   ?>
                                        <tr>
                                            <?php $attendanceStudent = $studentMap[(string) ($attendance['studentId'] ?? '')] ?? []; ?>
                                            <td><?= e(trim(($attendanceStudent['firstName'] ?? '') . ' ' . ($attendanceStudent['lastName'] ?? '')) ?: ($attendance['studentName'] ?? $attendance['studentId'] ?? '-')) ?></td>
                                            <td><?= e($attendance['roomNumber'] ?? '-') ?></td>
                                            <td><?= e($attendance['status'] ?? '-') ?></td>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view.php?id=' . urlencode((string) ($attendance['id'] ?? ''))) ?>">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today's Visitors</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Room</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((new VisitorService())->todayByHouse($houseId) as $visitor):   ?>
                                        <tr>
                                            <td><?= e($visitor['name'] ?? $visitor['visitorName'] ?? '-') ?></td>
                                            <td><?= e($visitor['roomNumber'] ?? '-') ?></td>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <br>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Open Incidents</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Room</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((new IncidentService())->byHouse($houseId, true) as $incident):   ?>
                                        <tr>
                                            <td><?= e($incident['title'] ?? 'Incident') ?></td>
                                            <td><?= e($incident['roomNumber'] ?? '-') ?></td>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/incidents/index.php') ?>">View log</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <br>

        
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
