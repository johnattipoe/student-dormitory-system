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
        if ($parent === $dir) break;
        $dir = $parent;
    }
}

$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\RoomService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$today = date('Y-m-d');
$attendance = AttendanceService::todayByHouse($houseId);
$visitors = (new VisitorService())->todayByHouse($houseId);
$roomStats = RoomService::occupancyStats($houseId);
$attendanceSummary = AttendanceService::summary($today, $houseId);
$openIncidents = (new IncidentService())->openByHouse($houseId);

// Fetch latest urgent announcements for house / school
$firebase = FirebaseService::getInstance();
$announcements = $firebase->getCollection('announcements', [], 50);
$recentAnnouncements = array_values(array_filter($announcements, function ($a) use ($houseId) {
    if (($a['status'] ?? 'published') !== 'published') return false;
    $aud = $a['audience'] ?? 'all';
    $targetRole = $a['targetRole'] ?? '';
    return $aud === 'all' || ($aud === 'role' && ($targetRole === ROLE_SENIOR_HOUSEPARENT || $targetRole === ROLE_HOUSE_MASTER));
}));
usort($recentAnnouncements, fn($a, $b) => strcmp((string)($b['publishedAt'] ?? $b['createdAt'] ?? ''), (string)($a['publishedAt'] ?? $a['createdAt'] ?? '')));
$topAnnouncements = array_slice($recentAnnouncements, 0, 3);

$totalStudents = count($students);
$occupancyRate = (int) ($roomStats['occupancyRate'] ?? 0);
$presentCount = (int) ($attendanceSummary['present'] ?? 0);
$absentCount = (int) ($attendanceSummary['absent'] ?? 0);
$vacantBeds = (int) ($roomStats['vacant'] ?? 0);
$totalRooms = (int) ($roomStats['totalRooms'] ?? count(RoomService::all($houseId)));

$pageTitle = 'Senior Houseparent Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/senior-houseparent/announcements/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        
        <!-- Welcome Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-house-door-fill text-primary me-2"></i>Welcome, <?= e($userName) ?>
                </h4>
                <p class="text-muted mb-0">
                    Live dormitory command & supervisory overview for <strong><?= e($houseName) ?></strong> &bull; <?= e(date('l, F j, Y')) ?>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= url('views/senior-houseparent/attendance/mark-attendance/mark-attendance.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-check2-circle me-1"></i> Mark Roll Call
                </a>
                <a href="<?= url('views/senior-houseparent/announcements/create/create.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-megaphone me-1"></i> Post Announcement
                </a>
                <a href="<?= url('views/senior-houseparent/emergency-alerts/broadcast/broadcast.php') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-broadcast me-1"></i> Emergency Alert
                </a>
            </div>
        </div>

        <!-- Primary KPI Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Assigned Students</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalStudents) ?></h3>
                            <span class="small text-muted"><i class="bi bi-house me-1"></i><?= e($houseName) ?></span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary">
                            <i class="bi bi-mortarboard fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present Today</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $presentCount) ?></h3>
                            <span class="small text-muted"><i class="bi bi-clock-history me-1"></i><?= e((string)$absentCount) ?> absent</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success">
                            <i class="bi bi-calendar-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Visitors Today</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($visitors)) ?></h3>
                            <span class="small text-muted">Active on campus log</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info">
                            <i class="bi bi-people fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= $openIncidents > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= $openIncidents > 0 ? 'danger' : 'dark' ?>"><?= e((string) $openIncidents) ?></h3>
                            <span class="small text-muted"><?= $openIncidents > 0 ? 'Requires follow-up' : 'All resolved' ?></span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="bi bi-shield-exclamation fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Dormitory Capacity & Analytics Strip -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold">DORMITORY OCCUPANCY</small>
                        <span class="badge bg-<?= $occupancyRate >= 95 ? 'danger' : ($occupancyRate >= 75 ? 'warning' : 'primary') ?>"><?= $occupancyRate ?>%</span>
                    </div>
                    <div class="progress my-2" style="height: 8px;">
                        <div class="progress-bar bg-<?= $occupancyRate >= 95 ? 'danger' : ($occupancyRate >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $occupancyRate ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $totalStudents ?> allocated students</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">VACANT SPACES</small>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= e((string) $vacantBeds) ?> Beds Available</div>
                    <small class="text-muted">Across <?= e((string) $totalRooms) ?> room blocks</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">ROLL CALL RATE</small>
                    <?php $attRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0; ?>
                    <div class="fs-4 fw-bold text-success mt-1"><?= $attRate ?>% Present</div>
                    <small class="text-muted"><?= $presentCount ?> verified in rooms</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">SUPERVISORY STATUS</small>
                    <div class="fs-4 fw-bold text-primary mt-1">Normal Operations</div>
                    <small class="text-muted">Curfew: 10:00 PM</small>
                </div>
            </div>
        </div>

        <!-- Main Workspace: 2-Column Split -->
        <div class="row g-4 mb-4">
            
            <!-- Left Column: Attendance & Visitors -->
            <div class="col-lg-8">
                
                <!-- Today's Attendance Table -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i>Today's Dormitory Roll Call</h6>
                            <small class="text-muted">Live attendance log for <?= e($houseName) ?></small>
                        </div>
                        <a href="<?= url('views/senior-houseparent/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            View All Attendance <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                        <th class="text-end">Verification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-calendar-x fs-3 d-block text-secondary mb-1"></i>
                                                No attendance records submitted yet today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($attendance, 0, 6) as $record): ?>
                                            <?php
                                            $st = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
                                            $stName = trim((($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''))) ?: ($record['studentId'] ?? 'Student');
                                            $admNo = $st['admissionNo'] ?? '';
                                            $stRoom = $st['roomNumber'] ?? ($st['roomId'] ?? '—');
                                            $status = strtolower((string) ($record['status'] ?? 'present'));
                                            $badgeClass = match($status) {
                                                'present' => 'bg-success',
                                                'absent' => 'bg-danger',
                                                'exeat', 'leave' => 'bg-info',
                                                'sick', 'infirmary' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };
                                            $rawTime = (string) ($record['timestamp'] ?? $record['createdAt'] ?? '');
                                            $timeLabel = $rawTime !== '' ? (date('h:i A', strtotime($rawTime)) ?: $rawTime) : 'Verified';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($stName) ?></strong>
                                                    <?php if ($admNo !== ''): ?>
                                                        <small class="text-muted d-block font-monospace">[<?= e($admNo) ?>]</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-light text-dark border">Room <?= e((string)$stRoom) ?></span></td>
                                                <td>
                                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst(e($status)) ?></span>
                                                </td>
                                                <td class="text-end small text-muted">
                                                    <i class="bi bi-check2 me-1 text-success"></i><?= e($timeLabel) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Today's Visitors Table -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-info"></i>Today's House Visitors</h6>
                            <small class="text-muted">Registered visitors for <?= e($houseName) ?> students</small>
                        </div>
                        <a href="<?= url('views/senior-houseparent/visitors/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Visitor Records <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Student Visited</th>
                                        <th>Purpose / Relation</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visitors)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i>
                                                No visitors recorded for your house today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($visitors, 0, 5) as $visitor): ?>
                                            <?php
                                            $vSt = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null;
                                            $vStName = trim((($vSt['firstName'] ?? '') . ' ' . ($vSt['lastName'] ?? ''))) ?: ($visitor['studentId'] ?? '—');
                                            $vStatus = strtolower((string) ($visitor['status'] ?? 'pending'));
                                            $vBadge = match($vStatus) {
                                                'checked_in', 'active' => 'bg-success',
                                                'checked_out' => 'bg-secondary',
                                                default => 'bg-primary-subtle text-primary border',
                                            };
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($visitor['visitorName'] ?? $visitor['name'] ?? 'Visitor') ?></strong>
                                                    <small class="text-muted d-block"><?= e($visitor['phone'] ?? '') ?></small>
                                                </td>
                                                <td><?= e($vStName) ?></td>
                                                <td><span class="small text-muted"><?= e($visitor['purpose'] ?? $visitor['relationship'] ?? 'Guardian Visit') ?></span></td>
                                                <td class="text-end">
                                                    <span class="badge <?= $vBadge ?>"><?= ucfirst(str_replace('_', ' ', e($vStatus))) ?></span>
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

            <!-- Right Column: Quick Action Grid & Urgent Bulletins -->
            <div class="col-lg-4">
                
                <!-- Quick Supervisory Actions -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Supervisory Quick Actions</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= url('views/senior-houseparent/students/index/index.php') ?>" class="btn btn-outline-primary w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-mortarboard fs-3 mb-1"></i>
                                    <span class="small fw-bold">Students</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/senior-houseparent/attendance/index/index.php') ?>" class="btn btn-outline-success w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-calendar-check fs-3 mb-1"></i>
                                    <span class="small fw-bold">Roll Call</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/senior-houseparent/rooms/index/index.php') ?>" class="btn btn-outline-info w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-door-open fs-3 mb-1"></i>
                                    <span class="small fw-bold">Rooms & Beds</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/senior-houseparent/incidents/create/create.php') ?>" class="btn btn-outline-danger w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-shield-exclamation fs-3 mb-1"></i>
                                    <span class="small fw-bold">Report Incident</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/parent-messages/create/create.php') ?>" class="btn btn-outline-secondary w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-chat-left-text fs-3 mb-1"></i>
                                    <span class="small fw-bold">Message Parents</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/senior-houseparent/activity-logs/index.php') ?>" class="btn btn-outline-dark w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-clock-history fs-3 mb-1"></i>
                                    <span class="small fw-bold">Activity Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Announcements & Advisories -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Recent Bulletins</h6>
                        <a href="<?= url('views/senior-houseparent/announcements/index.php') ?>" class="small text-decoration-none">View All</a>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($topAnnouncements)): ?>
                            <p class="text-muted small text-center my-3">No active bulletins posted.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($topAnnouncements as $ann): ?>
                                    <?php
                                    $aType = $ann['type'] ?? 'info';
                                    $aBadge = match($aType) {
                                        'danger' => 'bg-danger',
                                        'warning' => 'bg-warning text-dark',
                                        default => 'bg-primary',
                                    };
                                    ?>
                                    <div class="p-2 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge <?= $aBadge ?>"><?= ucfirst(e($aType)) ?></span>
                                            <?php if (!empty($ann['isUrgent'])): ?>
                                                <span class="badge bg-danger small">Urgent</span>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="fw-bold mb-1 small text-dark"><?= e($ann['title'] ?? 'Notice') ?></h6>
                                        <p class="text-muted small mb-0"><?= e(mb_strimwidth((string)($ann['message'] ?? ''), 0, 75, '...')) ?></p>
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
