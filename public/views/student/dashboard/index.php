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
use App\Services\BedService;
use App\Services\HouseService;
use App\Services\IncidentService;
use App\Services\NotificationService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;

$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$student = null;
$room = null;
$house = null;
$assignedBed = null;
$attendance = [];
$visitors = [];
$incidents = [];
$userNotifications = [];
$dashboardNotice = null;

try {
    if ($studentId) {
        $student = StudentService::find((string) $studentId);
    }

    if (!$student && !empty($currentUser['email'])) {
        foreach (StudentService::all() as $candidate) {
            if ((string) ($candidate['email'] ?? '') === (string) $currentUser['email']) {
                $student = $candidate;
                $studentId = $candidate['id'] ?? $studentId;
                break;
            }
        }
    }

    if ($studentId) {
        $attendance = AttendanceService::history((string) $studentId, 60);
        $visitors = (new VisitorService())->studentVisitors((string) $studentId);
        $incidents = (new IncidentService())->studentIncidents((string) $studentId);
    }

    $studentDocumentId = (string) ($student['id'] ?? $studentId ?? '');
    foreach (BedService::all() as $bed) {
        if ((string) ($bed['studentId'] ?? '') === $studentDocumentId) {
            $assignedBed = $bed;
            break;
        }
    }

    $roomId = (string) ($student['roomId'] ?? $assignedBed['roomId'] ?? '');
    if ($roomId !== '') {
        $room = RoomService::find($roomId);
    }

    $houseId = (string) ($student['houseId'] ?? $room['houseId'] ?? '');
    if ($houseId !== '') {
        $house = HouseService::find($houseId);
    }

    $userNotifications = (new NotificationService())->forUser($currentUser['uid'] ?? $currentUser['id'] ?? null);
} catch (Throwable $e) {
    $dashboardNotice = 'Some dashboard data is temporarily unavailable. Please refresh later.';
}

usort($attendance, static fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
usort($visitors, static fn($a, $b) => strcmp((string) ($b['visitDate'] ?? $b['createdAt'] ?? ''), (string) ($a['visitDate'] ?? $a['createdAt'] ?? '')));
usort($incidents, static fn($a, $b) => strcmp((string) ($b['reportedAt'] ?? $b['createdAt'] ?? ''), (string) ($a['reportedAt'] ?? $a['createdAt'] ?? '')));
usort($userNotifications, static fn($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') {
    $studentName = $currentUser['name'] ?? 'Student';
}
$studentInitials = strtoupper(substr((string) ($student['firstName'] ?? $studentName), 0, 1) . substr((string) ($student['lastName'] ?? ''), 0, 1));
$studentInitials = $studentInitials !== '' ? $studentInitials : 'S';

$summary = [
    'present' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'present')),
    'absent' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'absent')),
    'late' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'late')),
    'excused' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'excused')),
];
$totalAttendance = count($attendance);
$attendanceRate = $totalAttendance > 0 ? round((($summary['present'] + $summary['excused']) / $totalAttendance) * 100) : 0;
$todayAttendance = array_values(array_filter($attendance, static fn($r) => ($r['date'] ?? '') === date('Y-m-d')));
$openIncidents = array_values(array_filter($incidents, static fn($item) => ($item['status'] ?? 'open') === 'open'));
$pendingVisitors = array_values(array_filter($visitors, static fn($item) => ($item['status'] ?? 'pending') === 'pending'));
$unreadNotifications = array_values(array_filter($userNotifications, static fn($item) => empty($item['read'])));
$latestAttendance = $attendance[0] ?? null;
$roomNumber = (string) ($room['roomNumber'] ?? 'Not assigned');
$bedNumber = (string) ($assignedBed['bedNumber'] ?? 'Not assigned');
$houseName = (string) ($house['name'] ?? $house['houseName'] ?? 'Not assigned');
$roomCapacity = (int) ($room['capacity'] ?? 0);
$roomOccupied = (int) ($room['occupied'] ?? 0);
$roomRate = $roomCapacity > 0 ? min(100, round(($roomOccupied / $roomCapacity) * 100)) : 0;

$pageTitle = 'Student Dashboard';
$pageStyles = ['student.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/student/reports/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/student/beds/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="student-dashboard-hero mb-4">
            <div class="student-dashboard-avatar" aria-hidden="true"><?= e($studentInitials) ?></div>
            <div>
                <span class="student-settings-kicker">Student command center</span>
                <h1>Welcome, <?= e($studentName) ?></h1>
                <p>Your attendance, residence, visitors, incidents, and notifications are summarized here.</p>
                <div class="student-settings-badges">
                    <span class="badge bg-primary"><i class="bi bi-house-door me-1"></i><?= e($roomNumber) ?></span>
                    <span class="badge bg-info"><i class="bi bi-bed me-1"></i><?= e($bedNumber) ?></span>
                    <span class="badge bg-success"><i class="bi bi-building me-1"></i><?= e($houseName) ?></span>
                </div>
            </div>
            <div class="student-dashboard-actions">
                <a href="<?= url('views/student/attendance/index.php') ?>" class="btn btn-light"><i class="bi bi-calendar-check me-1"></i>Attendance</a>
                <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Visitors</a>
            </div>
        </section>

        <?php if ($dashboardNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dashboardNotice) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="student-dashboard-stat"><span class="student-dashboard-stat-icon green"><i class="bi bi-graph-up-arrow"></i></span><div><small>Attendance rate</small><strong><?= e((string) $attendanceRate) ?>%</strong></div></div></div>
            <div class="col-md-3"><div class="student-dashboard-stat"><span class="student-dashboard-stat-icon blue"><i class="bi bi-check-circle"></i></span><div><small>Present</small><strong><?= e((string) $summary['present']) ?></strong></div></div></div>
            <div class="col-md-3"><div class="student-dashboard-stat"><span class="student-dashboard-stat-icon orange"><i class="bi bi-flag"></i></span><div><small>Open incidents</small><strong><?= e((string) count($openIncidents)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="student-dashboard-stat"><span class="student-dashboard-stat-icon purple"><i class="bi bi-bell"></i></span><div><small>Unread alerts</small><strong><?= e((string) count($unreadNotifications)) ?></strong></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="student-dashboard-main-card mb-4">
                    <div class="student-dashboard-card-header">
                        <div>
                            <span class="student-settings-kicker">Attendance</span>
                            <h2>Recent attendance</h2>
                            <p><?= e((string) $totalAttendance) ?> recent record<?= $totalAttendance === 1 ? '' : 's' ?> loaded for your account.</p>
                        </div>
                        <strong class="student-dashboard-rate"><?= e((string) $attendanceRate) ?>%</strong>
                    </div>
                    <div class="progress student-dashboard-progress mb-3" role="progressbar" aria-valuenow="<?= e((string) $attendanceRate) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= e((string) $attendanceRate) ?>%"></div>
                    </div>

                    <?php if ($attendance): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Date</th><th>Status</th><th>Notes</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($attendance, 0, 6) as $record): ?>
                                    <?php
                                    $status = strtolower((string) ($record['status'] ?? 'present'));
                                    $badge = match ($status) {
                                        'present' => 'success',
                                        'absent' => 'danger',
                                        'late' => 'warning',
                                        'excused' => 'info',
                                        default => 'secondary',
                                    };
                                    ?>
                                    <tr>
                                        <td><?= e($record['date'] ?? '-') ?></td>
                                        <td><span class="badge bg-<?= e($badge) ?>"><?= e(ucfirst($status)) ?></span></td>
                                        <td><?= e($record['reason'] ?? $record['notes'] ?? '-') ?></td>
                                        <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/attendance/view.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="student-dashboard-empty"><i class="bi bi-calendar-x"></i><p>No attendance records found yet.</p></div>
                    <?php endif; ?>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="student-dashboard-main-card h-100">
                            <div class="student-dashboard-card-header">
                                <div><span class="student-settings-kicker">Visitors</span><h2>Visitor activity</h2><p><?= e((string) count($pendingVisitors)) ?> pending request<?= count($pendingVisitors) === 1 ? '' : 's' ?>.</p></div>
                            </div>
                            <?php if ($visitors): ?>
                                <div class="student-dashboard-list">
                                    <?php foreach (array_slice($visitors, 0, 5) as $visitor): ?>
                                        <a href="<?= url('views/student/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">
                                            <span><i class="bi bi-person"></i><?= e($visitor['visitorName'] ?? 'Visitor') ?></span>
                                            <strong><?= e(ucfirst((string) ($visitor['status'] ?? 'pending'))) ?></strong>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="student-dashboard-empty"><i class="bi bi-people"></i><p>No visitor activity yet.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="student-dashboard-main-card h-100">
                            <div class="student-dashboard-card-header">
                                <div><span class="student-settings-kicker">Incidents</span><h2>Incident records</h2><p><?= e((string) count($openIncidents)) ?> open incident<?= count($openIncidents) === 1 ? '' : 's' ?>.</p></div>
                            </div>
                            <?php if ($incidents): ?>
                                <div class="student-dashboard-list">
                                    <?php foreach (array_slice($incidents, 0, 5) as $incident): ?>
                                        <a href="<?= url('views/student/incidents/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">
                                            <span><i class="bi bi-flag"></i><?= e($incident['title'] ?? 'Incident') ?></span>
                                            <strong><?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?></strong>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="student-dashboard-empty"><i class="bi bi-shield-check"></i><p>No incidents reported.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="student-dashboard-side-card mb-4">
                    <span class="student-settings-kicker">Residence</span>
                    <h2>Room overview</h2>
                    <div class="student-dashboard-room-card">
                        <i class="bi bi-house-door"></i>
                        <strong><?= e($roomNumber) ?></strong>
                        <span><?= e($houseName) ?></span>
                    </div>
                    <div class="progress student-dashboard-progress my-3">
                        <div class="progress-bar" style="width: <?= e((string) $roomRate) ?>%"></div>
                    </div>
                    <div class="student-dashboard-info-list">
                        <div><span>Bed</span><strong><?= e($bedNumber) ?></strong></div>
                        <div><span>Capacity</span><strong><?= e((string) $roomOccupied) ?> / <?= e((string) $roomCapacity) ?></strong></div>
                        <div><span>Room use</span><strong><?= e((string) $roomRate) ?>%</strong></div>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-house-door me-1"></i>Open room</a>
                        <a href="<?= url('views/student/beds/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-grid-3x3-gap me-1"></i>Open bed</a>
                    </div>
                </aside>

                <aside class="student-dashboard-side-card mb-4">
                    <span class="student-settings-kicker">Today</span>
                    <h2>At a glance</h2>
                    <div class="student-dashboard-today-grid">
                        <div><small>Records</small><strong><?= e((string) count($todayAttendance)) ?></strong></div>
                        <div><small>Present</small><strong><?= e((string) count(array_filter($todayAttendance, static fn($r) => ($r['status'] ?? '') === 'present'))) ?></strong></div>
                        <div><small>Late</small><strong><?= e((string) count(array_filter($todayAttendance, static fn($r) => ($r['status'] ?? '') === 'late'))) ?></strong></div>
                        <div><small>Absent</small><strong><?= e((string) count(array_filter($todayAttendance, static fn($r) => ($r['status'] ?? '') === 'absent'))) ?></strong></div>
                    </div>
                </aside>

                <aside class="student-dashboard-side-card">
                    <span class="student-settings-kicker">Quick actions</span>
                    <h2>Shortcuts</h2>
                    <div class="d-grid gap-2">
                        <a href="<?= url('views/student/attendance/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-calendar-check me-1"></i>Attendance records</a>
                        <a href="<?= url('views/visitors/request.php') ?>" class="btn btn-outline-success"><i class="bi bi-person-plus me-1"></i>Request visitor</a>
                        <a href="<?= url('views/student/incidents/create.php') ?>" class="btn btn-outline-danger"><i class="bi bi-flag me-1"></i>Report incident</a>
                        <a href="<?= url('views/student/notifications/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-bell me-1"></i>Notifications</a>
                        <a href="<?= url('views/student/settings/index.php') ?>" class="btn btn-secondary"><i class="bi bi-gear me-1"></i>Settings</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
