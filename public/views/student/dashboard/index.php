<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\HouseService;
use App\Services\IncidentService;
use App\Services\NotificationService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\FirebaseService;

$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$student = null; $room = null; $house = null; $assignedBed = null;
$attendance = []; $visitors = []; $incidents = []; $userNotifications = [];
$dashboardNotice = null;

try {
    if ($studentId) {
        $student = StudentService::find((string) $studentId);
    }
    if (!$student && !empty($currentUser['email'])) {
        foreach (StudentService::all() as $candidate) {
            if ((string)($candidate['email'] ?? '') === (string)$currentUser['email']) {
                $student = $candidate;
                $studentId = $candidate['id'] ?? $studentId;
                break;
            }
        }
    }
    if ($studentId) {
        $attendance = AttendanceService::history((string) $studentId, 60);
        $visitors   = (new VisitorService())->studentVisitors((string) $studentId);
        $incidents  = (new IncidentService())->studentIncidents((string) $studentId);
    }
    $studentDocumentId = (string)($student['id'] ?? $studentId ?? '');
    foreach (BedService::all() as $bed) {
        if ((string)($bed['studentId'] ?? '') === $studentDocumentId) {
            $assignedBed = $bed; break;
        }
    }
    $roomId = (string)($student['roomId'] ?? $assignedBed['roomId'] ?? '');
    if ($roomId !== '') $room = RoomService::find($roomId);
    $houseId = (string)($student['houseId'] ?? $room['houseId'] ?? '');
    if ($houseId !== '') $house = HouseService::find($houseId);
    $userNotifications = (new NotificationService())->forUser($currentUser['uid'] ?? $currentUser['id'] ?? null);

    // Fetch announcements visible to students
    $firebase = FirebaseService::getInstance();
    $allAnnouncements = $firebase->getCollection('announcements', [], 50);
    $studentAnnouncements = array_values(array_filter($allAnnouncements, function($a) {
        if (($a['status'] ?? 'published') !== 'published') return false;
        $aud = $a['audience'] ?? 'all';
        return $aud === 'all' || $aud === 'student';
    }));
    usort($studentAnnouncements, fn($a, $b) => strcmp((string)($b['publishedAt'] ?? $b['createdAt'] ?? ''), (string)($a['publishedAt'] ?? $a['createdAt'] ?? '')));
    $topAnnouncements = array_slice($studentAnnouncements, 0, 3);

} catch (Throwable $e) {
    $dashboardNotice = 'Some dashboard data is temporarily unavailable. Please refresh later.';
    $topAnnouncements = [];
}

usort($attendance,  static fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
usort($visitors,    static fn($a, $b) => strcmp((string)($b['visitDate'] ?? $b['createdAt'] ?? ''), (string)($a['visitDate'] ?? $a['createdAt'] ?? '')));
usort($incidents,   static fn($a, $b) => strcmp((string)($b['reportedAt'] ?? $b['createdAt'] ?? ''), (string)($a['reportedAt'] ?? $a['createdAt'] ?? '')));
usort($userNotifications, static fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));

$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') $studentName = $currentUser['name'] ?? 'Student';
$studentInitials = strtoupper(substr((string)($student['firstName'] ?? $studentName), 0, 1) . substr((string)($student['lastName'] ?? ''), 0, 1));
$studentInitials = $studentInitials ?: 'S';
$admNo    = (string)($student['admissionNo'] ?? $student['admNo'] ?? '');
$houseName  = (string)($house['name'] ?? $house['houseName'] ?? 'Not assigned');
$roomNumber = (string)($room['roomNumber'] ?? 'Not assigned');
$bedNumber  = (string)($assignedBed['bedNumber'] ?? 'Not assigned');
$roomCapacity = (int)($room['capacity'] ?? 0);
$roomOccupied = (int)($room['occupied'] ?? 0);
$roomRate = $roomCapacity > 0 ? min(100, round(($roomOccupied / $roomCapacity) * 100)) : 0;

$summary = [
    'present'  => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'present')),
    'absent'   => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'absent')),
    'late'     => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'late')),
    'excused'  => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'excused')),
];
$totalAttendance = count($attendance);
$attendanceRate  = $totalAttendance > 0 ? round((($summary['present'] + $summary['excused']) / $totalAttendance) * 100) : 0;
$openIncidents   = array_values(array_filter($incidents, static fn($i) => ($i['status'] ?? 'open') === 'open'));
$unreadNotifications = array_values(array_filter($userNotifications, static fn($n) => empty($n['read'])));

$pageTitle  = 'Student Dashboard';
$pageStyles = ['student.css'];
$navItems = [
    ['icon' => 'bi-speedometer2',    'label' => 'Dashboard',    'href' => url('views/student/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check',  'label' => 'Attendance',   'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-calendar2-week',  'label' => 'Exeat',        'href' => url('views/exeat/index.php')],
    ['icon' => 'bi-people',          'label' => 'Visitors',     'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag',            'label' => 'Incidents',    'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-megaphone',       'label' => 'Announcements','href' => url('views/student/announcements/index.php')],
    ['icon' => 'bi-bell',            'label' => 'Notifications','href' => url('views/student/notifications/index/index.php')],
    ['icon' => 'bi-person-circle',   'label' => 'Profile',      'href' => url('views/student/profile/index/index.php')],
    ['icon' => 'bi-house-door',      'label' => 'Room',         'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear',            'label' => 'Settings',     'href' => url('views/student/settings/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Welcome Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4"
                     style="width:56px;height:56px;flex-shrink:0;">
                    <?= e($studentInitials) ?>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">Welcome, <?= e($studentName) ?></h4>
                    <p class="text-muted mb-0 small">
                        <?php if ($admNo !== ''): ?><span class="badge bg-primary me-1"><?= e($admNo) ?></span><?php endif; ?>
                        <span class="badge bg-success me-1"><i class="bi bi-building me-1"></i><?= e($houseName) ?></span>
                        <span class="badge bg-info me-1">Room <?= e($roomNumber) ?></span>
                        <span class="badge bg-secondary">Bed <?= e($bedNumber) ?></span>
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/student/attendance/index/index.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-check me-1"></i> My Attendance
                </a>
                <a href="<?= url('views/visitors/request/request.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Request Visitor
                </a>
            </div>
        </div>

        <?php if ($dashboardNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($dashboardNotice) ?></div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Attendance Rate</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $attendanceRate) ?>%</h3>
                            <span class="small text-muted"><?= $summary['present'] ?> present &bull; <?= $summary['absent'] ?> absent</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success">
                            <i class="bi bi-graph-up-arrow fs-4"></i>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height:5px;">
                        <div class="progress-bar bg-success" style="width:<?= $attendanceRate ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Room Occupancy</span>
                            <h3 class="fw-bold my-1 text-primary"><?= $roomRate ?>%</h3>
                            <span class="small text-muted"><?= $roomOccupied ?> / <?= $roomCapacity ?> beds used</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary">
                            <i class="bi bi-house-door fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= count($openIncidents) > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= count($openIncidents) > 0 ? 'danger' : 'dark' ?>"><?= count($openIncidents) ?></h3>
                            <span class="small text-muted"><?= count($incidents) ?> total logged</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="bi bi-flag fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Unread Alerts</span>
                            <h3 class="fw-bold my-1 text-info"><?= count($unreadNotifications) ?></h3>
                            <span class="small text-muted"><?= count($userNotifications) ?> total received</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info">
                            <i class="bi bi-bell fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="row g-4 mb-4">

            <!-- Left: Attendance + Visitors/Incidents -->
            <div class="col-lg-8">

                <!-- Attendance -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-success"></i>Recent Attendance</h6>
                            <small class="text-muted"><?= $totalAttendance ?> records &bull; <?= $attendanceRate ?>% overall rate</small>
                        </div>
                        <a href="<?= url('views/student/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Full History <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Date</th><th>Status</th><th>Notes</th><th class="text-end">Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x fs-3 d-block text-secondary mb-1"></i>
                                            No attendance records yet.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($attendance, 0, 6) as $rec): ?>
                                            <?php
                                            $st = strtolower((string)($rec['status'] ?? 'present'));
                                            $bc = match($st) { 'present' => 'bg-success', 'absent' => 'bg-danger', 'late' => 'bg-warning text-dark', 'excused' => 'bg-info', default => 'bg-secondary' };
                                            ?>
                                            <tr>
                                                <td><?= e($rec['date'] ?? '—') ?></td>
                                                <td><span class="badge <?= $bc ?>"><?= ucfirst(e($st)) ?></span></td>
                                                <td><small class="text-muted"><?= e(mb_strimwidth((string)($rec['reason'] ?? $rec['notes'] ?? ''), 0, 40, '…')) ?: '—' ?></small></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/attendance/view/view.php?id=' . urlencode((string)($rec['id'] ?? ''))) ?>">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Visitors & Incidents: 2-col -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card stat-card shadow-sm h-100">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-info"></i>My Visitors</h6>
                                <a href="<?= url('views/student/visitors/index/index.php') ?>" class="small text-decoration-none">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($visitors)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i> No visitors yet.
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach (array_slice($visitors, 0, 5) as $v): ?>
                                            <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                                                <span class="small fw-bold"><i class="bi bi-person me-1 text-muted"></i><?= e($v['visitorName'] ?? 'Visitor') ?></span>
                                                <span class="badge bg-<?= ($v['status'] ?? '') === 'inside' ? 'success' : (($v['status'] ?? '') === 'checked_out' ? 'secondary' : 'primary-subtle text-primary border') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', e($v['status'] ?? 'pending'))) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card stat-card shadow-sm h-100">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2 text-danger"></i>My Incidents</h6>
                                <a href="<?= url('views/student/incidents/index/index.php') ?>" class="small text-decoration-none">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($incidents)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-shield-check fs-3 d-block text-secondary mb-1"></i> No incidents logged.
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach (array_slice($incidents, 0, 5) as $inc): ?>
                                            <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                                                <span class="small fw-bold"><i class="bi bi-flag me-1 text-muted"></i><?= e(mb_strimwidth((string)($inc['title'] ?? 'Incident'), 0, 25, '…')) ?></span>
                                                <span class="badge bg-<?= ($inc['status'] ?? 'open') === 'resolved' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst(e($inc['status'] ?? 'open')) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Room Card + Quick Actions + Bulletins -->
            <div class="col-lg-4">

                <!-- Room Card -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-house-door me-2 text-primary"></i>My Residence</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary"><i class="bi bi-house-door fs-3"></i></div>
                            <div>
                                <div class="fw-bold fs-5">Room <?= e($roomNumber) ?></div>
                                <div class="text-muted small"><?= e($houseName) ?></div>
                            </div>
                        </div>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-light">
                                    <div class="fw-bold"><?= e($bedNumber) ?></div>
                                    <small class="text-muted">My Bed</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-light">
                                    <div class="fw-bold"><?= $roomOccupied ?></div>
                                    <small class="text-muted">Occupied</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-light">
                                    <div class="fw-bold"><?= $roomCapacity ?></div>
                                    <small class="text-muted">Capacity</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-1 d-flex justify-content-between">
                            <small class="text-muted">Room usage</small>
                            <small class="fw-bold"><?= $roomRate ?>%</small>
                        </div>
                        <div class="progress mb-3" style="height:6px;">
                            <div class="progress-bar bg-primary" style="width:<?= $roomRate ?>%;"></div>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-house-door me-1"></i>Room Details</a>
                            <a href="<?= url('views/student/beds/index.php') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-grid-3x3-gap me-1"></i>Bed Overview</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body p-3 d-grid gap-2">
                        <a href="<?= url('views/visitors/request/request.php') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-person-plus me-1"></i>Request a Visitor</a>
                        <a href="<?= url('views/student/incidents/create/create.php') ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-flag me-1"></i>Report Incident</a>
                        <a href="<?= url('views/student/notifications/index/index.php') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-bell me-1"></i>Notifications <?php if (count($unreadNotifications) > 0): ?><span class="badge bg-danger ms-1"><?= count($unreadNotifications) ?></span><?php endif; ?></a>
                        <a href="<?= url('views/student/profile/index/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-circle me-1"></i>My Profile</a>
                        <a href="<?= url('views/student/settings/index/index.php') ?>" class="btn btn-secondary btn-sm"><i class="bi bi-gear me-1"></i>Settings</a>
                    </div>
                </div>

                <!-- Bulletins -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Bulletins</h6>
                        <a href="<?= url('views/student/announcements/index.php') ?>" class="small text-decoration-none">All Notices</a>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($topAnnouncements)): ?>
                            <p class="text-muted small text-center my-2">No notices posted yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($topAnnouncements as $ann): ?>
                                    <div class="p-2 rounded-3 bg-light border">
                                        <div class="fw-bold small text-dark"><?= e($ann['title'] ?? 'Notice') ?></div>
                                        <p class="text-muted small mb-0"><?= e(mb_strimwidth((string)($ann['message'] ?? ''), 0, 70, '…')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
