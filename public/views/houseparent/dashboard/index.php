<?php
// Ensure bootstrap is loaded (safe for any view depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break; // reached root
        $dir = $parent;
    }
}

$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$stats = [
    'students' => count($students),
    'attendance' => count(AttendanceService::todayByHouse($houseId)),
    'visitors' => count((new VisitorService())->todayByHouse($houseId)),
    'incidents' => (new IncidentService())->openByHouse($houseId),
];

$attendance = AttendanceService::todayByHouse($houseId);
$visitors = (new VisitorService())->todayByHouse($houseId);
$roomStats = RoomService::occupancyStats($houseId);
$attendanceSummary = AttendanceService::summary(date('Y-m-d'), $houseId);

$pageTitle = 'Senior Houseparent Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php'), 'active' => true],
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
        <div class="mb-4">
            <h5 class="mb-1">Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
            <p class="text-muted mb-0">Live overview for your assigned house.</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Students</div>
                    <div class="fs-2 fw-bold"><?= e((string) $stats['students']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Today Attendance</div>
                    <div class="fs-2 fw-bold"><?= e((string) $stats['attendance']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Today's Visitors</div>
                    <div class="fs-2 fw-bold"><?= e((string) $stats['visitors']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Open Incidents</div>
                    <div class="fs-2 fw-bold"><?= e((string) $stats['incidents']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4"><div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Present today</small><strong class="fs-2 text-success"><?= e((string) ($attendanceSummary['present'] ?? 0)) ?></strong></div></div><div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Absent today</small><strong class="fs-2 text-danger"><?= e((string) ($attendanceSummary['absent'] ?? 0)) ?></strong></div></div><div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Vacant spaces</small><strong class="fs-2"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></strong></div></div><div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupancy rate</small><strong class="fs-2"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</strong></div></div></div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="card-title">Today's attendance</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No attendance submitted yet today.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance as $record): ?>
                                            <?php
                                            $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
                                            $studentName = trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
                                            ?>
                                            <tr>
                                                <td><?= e($studentName ?: ($record['studentId'] ?? '-')) ?></td>
                                                <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="card-title">Quick actions</div>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?= e(url('views/houseparent/students/index.php')) ?>" class="btn btn-outline-primary text-start">
                                <i class="bi bi-people me-2"></i>Student Directory
                            </a>
                            <a href="<?= e(url('views/houseparent/attendance/index.php')) ?>" class="btn btn-outline-success text-start">
                                <i class="bi bi-calendar-check me-2"></i>Attendance Records
                            </a>
                            <a href="<?= e(url('views/houseparent/visitors/index.php')) ?>" class="btn btn-outline-info text-start">
                                <i class="bi bi-people-fill me-2"></i>Visitor Log
                            </a>
                            <a href="<?= e(url('views/houseparent/attendance/mark-attendance.php')) ?>" class="btn btn-outline-secondary text-start">
                                <i class="bi bi-pencil-square me-2"></i>Mark Attendance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today's visitors</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visitors)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No visitors recorded for today.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($visitors as $visitor): ?>
                                            <?php
                                            $visitorStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null;
                                            $visitorStudentName = trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')));
                                            ?>
                                            <tr>
                                                <td><?= e($visitor['visitorName'] ?? '-') ?></td>
                                                <td><?= e($visitorStudentName ?: ($visitor['studentId'] ?? '—')) ?></td>
                                                <td><span class="badge bg-secondary"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
