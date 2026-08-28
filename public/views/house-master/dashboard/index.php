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
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'House Master';
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$assignedHouse = $houseId !== '' ? HouseService::find($houseId) : null;
$assignedHouseName = $assignedHouse['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$today = date('Y-m-d');
$todayAttendance = AttendanceService::todayByHouse($houseId);
$todayVisitors = (new VisitorService())->todayByHouse($houseId);
$openIncidents = (new IncidentService())->byHouse($houseId, true);
$attendanceSummary = AttendanceService::summary($today, $houseId);
$roomStats = RoomService::occupancyStats($houseId);

$totalStudents = count($students);
$totalRooms = RoomService::count($houseId);
$presentCount = (int) ($attendanceSummary['present'] ?? 0);
$absentCount = (int) ($attendanceSummary['absent'] ?? 0);
$vacantBeds = (int) ($roomStats['vacant'] ?? 0);
$occupancyRate = (int) ($roomStats['occupancyRate'] ?? 0);

// Fetch recent announcements
$firebase = FirebaseService::getInstance();
$announcements = $firebase->getCollection('announcements', [], 50);
$recentAnnouncements = array_values(array_filter($announcements, function ($a) use ($houseId) {
    if (($a['status'] ?? 'published') !== 'published') return false;
    $aud = $a['audience'] ?? 'all';
    $targetRole = $a['targetRole'] ?? '';
    return $aud === 'all' || ($aud === 'role' && ($targetRole === ROLE_HOUSE_MASTER || $targetRole === ROLE_SENIOR_HOUSEPARENT));
}));
usort($recentAnnouncements, fn($a, $b) => strcmp((string)($b['publishedAt'] ?? $b['createdAt'] ?? ''), (string)($a['publishedAt'] ?? $a['createdAt'] ?? '')));
$topAnnouncements = array_slice($recentAnnouncements, 0, 3);

$pageTitle = 'House Master Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/house-master/announcements/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
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
                    <i class="bi bi-building-fill text-primary me-2"></i>Welcome, <?= e($userName) ?>
                </h4>
                <p class="text-muted mb-0">
                    House Supervisory Command for <strong><?= e($assignedHouseName) ?></strong> &bull; <?= e(date('l, F j, Y')) ?>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-check2-circle me-1"></i> Verify Attendance
                </a>
                <a href="<?= url('views/house-master/incidents/create/create.php') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-shield-exclamation me-1"></i> Log Incident
                </a>
                <a href="<?= url('views/house-master/emergency-alerts/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-telephone-inbound me-1"></i> Emergency Desk
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
                            <span class="small text-muted"><i class="bi bi-door-open me-1"></i><?= e((string)$totalRooms) ?> room blocks</span>
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
                            <span class="small text-muted"><i class="bi bi-person-x me-1"></i><?= e((string)$absentCount) ?> absent</span>
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
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($todayVisitors)) ?></h3>
                            <span class="small text-muted">Campus register</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info">
                            <i class="bi bi-people fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= count($openIncidents) > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= count($openIncidents) > 0 ? 'danger' : 'dark' ?>"><?= e((string) count($openIncidents)) ?></h3>
                            <span class="small text-muted"><?= count($openIncidents) > 0 ? 'Pending resolution' : 'No active issues' ?></span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="bi bi-flag fs-4"></i>
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
                        <small class="text-muted fw-bold">HOUSE OCCUPANCY</small>
                        <span class="badge bg-<?= $occupancyRate >= 95 ? 'danger' : ($occupancyRate >= 75 ? 'warning' : 'primary') ?>"><?= $occupancyRate ?>%</span>
                    </div>
                    <div class="progress my-2" style="height: 8px;">
                        <div class="progress-bar bg-<?= $occupancyRate >= 95 ? 'danger' : ($occupancyRate >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $occupancyRate ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $totalStudents ?> students housed</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">VACANT BEDS</small>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= e((string) $vacantBeds) ?> Available</div>
                    <small class="text-muted">In <?= e((string) $totalRooms) ?> room blocks</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">ATTENDANCE RATE</small>
                    <?php $attRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0; ?>
                    <div class="fs-4 fw-bold text-success mt-1"><?= $attRate ?>% Verified</div>
                    <small class="text-muted"><?= $presentCount ?> students present</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">DORMITORY STATUS</small>
                    <div class="fs-4 fw-bold text-primary mt-1">Normal Shift</div>
                    <small class="text-muted">Curfew: 10:00 PM</small>
                </div>
            </div>
        </div>

        <!-- Main Workspace: 2 Columns -->
        <div class="row g-4 mb-4">
            
            <!-- Left Column: Attendance & Visitors -->
            <div class="col-lg-8">
                
                <!-- Today's Attendance Table -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i>Today's Roll Call Status</h6>
                            <small class="text-muted">Supervisory attendance record for <?= e($assignedHouseName) ?></small>
                        </div>
                        <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            View All <i class="bi bi-arrow-right ms-1"></i>
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
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($todayAttendance)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-calendar-x fs-3 d-block text-secondary mb-1"></i>
                                                No attendance submitted yet today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($todayAttendance, 0, 6) as $attendance): ?>
                                            <?php
                                            $st = $studentMap[(string) ($attendance['studentId'] ?? '')] ?? [];
                                            $stName = trim((($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''))) ?: ($attendance['studentName'] ?? $attendance['studentId'] ?? 'Student');
                                            $admNo = $st['admissionNo'] ?? '';
                                            $stRoom = $attendance['roomNumber'] ?? ($st['roomNumber'] ?? ($st['roomId'] ?? '—'));
                                            $status = strtolower((string) ($attendance['status'] ?? 'present'));
                                            $badgeClass = match($status) {
                                                'present' => 'bg-success',
                                                'absent' => 'bg-danger',
                                                'exeat', 'leave' => 'bg-info',
                                                'sick', 'infirmary' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };
                                            $attId = (string) ($attendance['id'] ?? '');
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
                                                <td class="text-end">
                                                    <?php if ($attId !== ''): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode($attId)) ?>">View</a>
                                                    <?php endif; ?>
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
                            <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-info"></i>Today's Visitors</h6>
                            <small class="text-muted">Registered guests for students in <?= e($assignedHouseName) ?></small>
                        </div>
                        <a href="<?= url('views/house-master/visitors/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Visitor Log <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Student</th>
                                        <th>Relation</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($todayVisitors)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i>
                                                No visitors recorded today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($todayVisitors, 0, 5) as $visitor): ?>
                                            <?php
                                            $vSt = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? [];
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
                                                <td><span class="small text-muted"><?= e($visitor['purpose'] ?? $visitor['relationship'] ?? 'Guardian') ?></span></td>
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

            <!-- Right Column: Quick Actions & Bulletins -->
            <div class="col-lg-4">
                
                <!-- Quick Supervisory Actions -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>House Management Actions</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= url('views/house-master/students/index/index.php') ?>" class="btn btn-outline-primary w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-mortarboard fs-3 mb-1"></i>
                                    <span class="small fw-bold">Students</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-success w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-calendar-check fs-3 mb-1"></i>
                                    <span class="small fw-bold">Attendance</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/house-master/rooms/index/index.php') ?>" class="btn btn-outline-info w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-door-open fs-3 mb-1"></i>
                                    <span class="small fw-bold">Rooms & Beds</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/house-master/incidents/create/create.php') ?>" class="btn btn-outline-danger w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-flag fs-3 mb-1"></i>
                                    <span class="small fw-bold">Log Incident</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/parent-messages/create/create.php') ?>" class="btn btn-outline-secondary w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-chat-left-text fs-3 mb-1"></i>
                                    <span class="small fw-bold">Message Parents</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/house-master/activity-logs/index.php') ?>" class="btn btn-outline-dark w-100 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="bi bi-clock-history fs-3 mb-1"></i>
                                    <span class="small fw-bold">Activity Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bulletins -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>House Bulletins</h6>
                        <a href="<?= url('views/house-master/announcements/index.php') ?>" class="small text-decoration-none">View All</a>
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
