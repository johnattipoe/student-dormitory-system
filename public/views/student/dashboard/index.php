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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\NotificationService;
use App\Services\VisitorService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$studentName = current_user()['name'] ?? 'Student';

$attendance = $studentId ? AttendanceService::history($studentId, 20) : [];
$visitors = $studentId ? (new VisitorService())->studentVisitors($studentId) : [];
$incidents = $studentId ? (new IncidentService())->studentIncidents($studentId) : [];
$notifications = new NotificationService();
$userNotifications = $notifications->forUser(current_user()['uid'] ?? null);

$summary = [
    'present' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'present')),
    'absent' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'absent')),
    'late' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'late')),
    'excused' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'excused')),
];
$totalAttendance = count($attendance);
$attendanceRate = $totalAttendance > 0 ? round((($summary['present'] + $summary['excused']) / $totalAttendance) * 100) : 0;
$todayAttendance = array_values(array_filter($attendance, fn($r) => ($r['date'] ?? '') === date('Y-m-d')));

$pageTitle = 'Student Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/student/reports/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Welcome, <?= e($studentName) ?></h5>
            <p class="text-muted mb-0">Your resident dashboard keeps track of your attendance, visits, incidents, and room activity.</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Attendance Rate</div>
                    <div class="fs-2 fw-bold <?= $attendanceRate >= 80 ? 'text-success' : ($attendanceRate >= 70 ? 'text-warning' : 'text-danger') ?>"><?= e((string) $attendanceRate) ?>%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Present</div>
                    <div class="fs-2 fw-bold text-success"><?= e((string) $summary['present']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Open Incidents</div>
                    <div class="fs-2 fw-bold text-warning"><?= e((string) count(array_filter($incidents, fn($item) => ($item['status'] ?? 'open') === 'open'))) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Notifications</div>
                    <div class="fs-2 fw-bold text-info"><?= e((string) count(array_filter($userNotifications, fn($item) => !($item['read'] ?? false)))) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="card-title">Recent Attendance</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No attendance records found yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($attendance, 0, 5) as $record): ?>
                                            <tr>
                                                <td><?= e($record['date'] ?? '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= (($record['status'] ?? 'present') === 'present') ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : (($record['status'] ?? '') === 'late' ? 'warning' : 'info')) ?>"><?= e(ucfirst($record['status'] ?? 'present')) ?></span>
                                                </td>
                                                <td><?= e($record['reason'] ?? $record['notes'] ?? '—') ?></td>
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
                        <div class="card-title">Quick Actions</div>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?= e(url('views/student/attendance/index.php')) ?>" class="btn btn-outline-primary text-start">
                                <i class="bi bi-calendar-check me-2"></i>My Attendance
                            </a>
                            <a href="<?= e(url('views/student/visitors/index.php')) ?>" class="btn btn-outline-info text-start">
                                <i class="bi bi-people me-2"></i>Visitor Requests
                            </a>
                            <a href="<?= e(url('views/student/incidents/index.php')) ?>" class="btn btn-outline-warning text-start">
                                <i class="bi bi-flag me-2"></i>Incident Log
                            </a>
                            <a href="<?= e(url('views/student/profile/index.php')) ?>" class="btn btn-outline-secondary text-start">
                                <i class="bi bi-person-circle me-2"></i>My Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Visitor Activity</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visitors)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No visitor records yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($visitors, 0, 5) as $visitor): ?>
                                            <tr>
                                                <td><?= e($visitor['visitorName'] ?? '-') ?></td>
                                                <td><span class="badge bg-secondary"><?= e(ucfirst($visitor['status'] ?? 'pending')) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Incidents</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($incidents)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No incidents reported.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($incidents, 0, 5) as $incident): ?>
                                            <tr>
                                                <td><?= e($incident['title'] ?? 'Incident') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= (($incident['status'] ?? 'open') === 'resolved') ? 'success' : 'warning' ?>"><?= e(ucfirst($incident['status'] ?? 'open')) ?></span>
                                                </td>
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

        <div class="card stat-card p-3 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Today at a glance</h6>
                <span class="badge bg-primary bg-opacity-10 text-primary"><?= e((string) count($todayAttendance)) ?> record(s)</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted">Present</div>
                        <div class="fs-4 fw-bold text-success"><?= e((string) count(array_filter($todayAttendance, fn($r) => ($r['status'] ?? '') === 'present'))) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted">Late</div>
                        <div class="fs-4 fw-bold text-warning"><?= e((string) count(array_filter($todayAttendance, fn($r) => ($r['status'] ?? '') === 'late'))) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted">Absent</div>
                        <div class="fs-4 fw-bold text-danger"><?= e((string) count(array_filter($todayAttendance, fn($r) => ($r['status'] ?? '') === 'absent'))) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted">Excused</div>
                        <div class="fs-4 fw-bold text-info"><?= e((string) count(array_filter($todayAttendance, fn($r) => ($r['status'] ?? '') === 'excused'))) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
