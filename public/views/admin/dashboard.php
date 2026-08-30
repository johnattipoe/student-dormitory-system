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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

if (!class_exists('App\\Services\\NotificationService', false)) {
    require_once APP_ROOT . '/app/services/NotificationService/NotificationService.php';
}

use App\Services\StudentService;
use App\Services\FirebaseService;
use App\Services\NotificationService;

$pageTitle = 'Admin Dashboard';

$firebase = FirebaseService::getInstance();
$user = current_user() ?? [];
$adminName = trim(($user['name'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))) ?: 'Administrator';

// Live counts
$studentCount       = count($firebase->getCollection(COL_STUDENTS, [], 1000));
$houseCount         = count($firebase->getCollection(COL_HOUSES, [], 100));
$roomCount          = count($firebase->getCollection(COL_ROOMS, [], 500));
$incidentCount      = count($firebase->getCollection(COL_INCIDENTS, [], 500));
$attendanceCount    = count($firebase->getCollection(COL_ATTENDANCE, [], 500));
$allocationCount    = count($firebase->getCollection(COL_ROOM_ALLOCATIONS, [], 500));
$activityLogCount   = count($firebase->getCollection(COL_ACTIVITY_LOGS, [], 500));
$allNotifications   = (new NotificationService())->all();
$notificationCount  = count($allNotifications);
$allIncidents       = $firebase->getCollection(COL_INCIDENTS, [], 500);
$openIncidentCount  = count(array_filter($allIncidents, static fn($i) => ($i['status'] ?? 'open') === 'open'));
$unreadNotificationCount = count(array_filter($allNotifications, static fn($n) => empty($n['read'])));

// Recent activity logs
$recentLogs = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 50);
usort($recentLogs, static fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$recentLogs = array_slice($recentLogs, 0, 8);

// Recent incidents
$recentIncidents = array_slice($allIncidents, 0, 6);
usort($recentIncidents, static fn($a, $b) => strcmp((string)($b['reportedAt'] ?? $b['createdAt'] ?? ''), (string)($a['reportedAt'] ?? $a['createdAt'] ?? '')));
$recentIncidents = array_slice($recentIncidents, 0, 5);

$occupancyPct = $roomCount > 0 ? min(100, round(($allocationCount / $roomCount) * 100)) : 0;

$navItems = [
    ['icon' => 'bi-speedometer2',        'label' => 'Dashboard',     'href' => url('views/admin/dashboard.php'),                     'active' => true],
    ['icon' => 'bi-people',              'label' => 'Users',         'href' => url('views/admin/users/index/index.php')],
    ['icon' => 'bi-mortarboard',         'label' => 'Students',      'href' => url('views/admin/students/index/index.php')],
    ['icon' => 'bi-building',            'label' => 'Houses',        'href' => url('views/admin/houses/index/index.php')],
    ['icon' => 'bi-door-closed',         'label' => 'Rooms',         'href' => url('views/admin/rooms/index/index.php')],
    ['icon' => 'bi-calendar-check',      'label' => 'Attendance',    'href' => url('views/admin/attendance/index/index.php')],
    ['icon' => 'bi-calendar2-week',      'label' => 'Exeat',         'href' => url('views/exeat/index.php')],
    ['icon' => 'bi-person-badge',        'label' => 'Visitors',      'href' => url('views/admin/visitors/index/index.php')],
    ['icon' => 'bi-exclamation-triangle','label' => 'Incidents',     'href' => url('views/admin/incidents/index/index.php')],
    ['icon' => 'bi-bar-chart',           'label' => 'Reports',       'href' => url('views/reports/dashboard/dashboard.php')],
    ['icon' => 'bi-bell',                'label' => 'Notifications', 'href' => url('views/admin/notifications/index/index.php')],
    ['icon' => 'bi-clock-history',       'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php')],
    ['icon' => 'bi-gear',                'label' => 'Settings',      'href' => url('views/admin/settings/index/index.php')],
];

$pageScripts = ['dashboard.js'];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">

        <!-- Welcome Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-shield-fill-check text-primary me-2"></i>Welcome, <?= e($adminName) ?>
                </h4>
                <p class="text-muted mb-0">
                    Campus Administration Control Center &bull; <?= e(date('l, F j, Y')) ?>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= url('views/admin/students/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Add Student
                </a>
                <a href="<?= url('views/admin/announcements/create/create.php') ?>" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-megaphone me-1"></i> Broadcast
                </a>
                <a href="<?= url('views/admin/profile.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-person-circle me-1"></i> My Profile
                </a>
            </div>
        </div>

        <!-- Primary KPI Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Students</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $studentCount) ?></h3>
                            <span class="small text-muted"><i class="bi bi-building me-1"></i><?= e((string) $houseCount) ?> houses</span>
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
                            <span class="text-muted small text-uppercase fw-semibold">Rooms & Allocations</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $roomCount) ?></h3>
                            <span class="small text-muted"><?= e((string) $allocationCount) ?> allocated &bull; <?= max(0, $roomCount - $allocationCount) ?> free</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success">
                            <i class="bi bi-door-closed fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= $openIncidentCount > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= $openIncidentCount > 0 ? 'danger' : 'dark' ?>"><?= e((string) $openIncidentCount) ?></h3>
                            <span class="small text-muted"><?= e((string) $incidentCount) ?> total logged</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Notifications</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $unreadNotificationCount) ?> Unread</h3>
                            <span class="small text-muted"><?= e((string) $notificationCount) ?> total sent</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info">
                            <i class="bi bi-bell fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Analytics Strip -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold">ROOM OCCUPANCY</small>
                        <span class="badge bg-<?= $occupancyPct >= 95 ? 'danger' : ($occupancyPct >= 75 ? 'warning' : 'primary') ?>"><?= $occupancyPct ?>%</span>
                    </div>
                    <div class="progress my-2" style="height: 8px;">
                        <div class="progress-bar bg-<?= $occupancyPct >= 95 ? 'danger' : ($occupancyPct >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $occupancyPct ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $allocationCount ?> of <?= $roomCount ?> rooms occupied</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">ATTENDANCE RECORDS</small>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= e((string) $attendanceCount) ?></div>
                    <small class="text-muted">Logged entries total</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">ACTIVITY EVENTS</small>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= e((string) $activityLogCount) ?></div>
                    <small class="text-muted">System-wide logs</small>
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">CAMPUS STATUS</small>
                    <div class="fs-4 fw-bold text-success mt-1">Operational</div>
                    <small class="text-muted"><?= e((string) $houseCount) ?> houses active</small>
                </div>
            </div>
        </div>

        <br>

        <!-- Main Workspace: 2 Columns -->
        <div class="row g-4 mb-4">

            <!-- Left: Recent Incidents + Chart -->
            <div class="col-lg-8">

                <!-- Chart Panel -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Campus Operational Overview</h6>
                            <small class="text-muted">Live service totals across the dormitory system</small>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success">Live data</span>
                    </div>
                    <div class="card-body">
                        <div style="min-height: 280px;">
                            <canvas id="dashboardChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Incidents Table -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Recent Incidents</h6>
                            <small class="text-muted">Latest disciplinary and safety incidents campus-wide</small>
                        </div>
                        <a href="<?= url('views/admin/incidents/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            View All <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Incident</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentIncidents)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            <i class="bi bi-shield-check fs-3 d-block text-secondary mb-1"></i>
                                            No incidents logged yet.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentIncidents as $inc): ?>
                                            <?php
                                            $iStatus   = strtolower((string)($inc['status'] ?? 'open'));
                                            $iPriority = strtolower((string)($inc['priority'] ?? 'low'));
                                            $sBadge    = match($iStatus) { 'resolved' => 'bg-success', 'closed' => 'bg-secondary', default => 'bg-danger' };
                                            $pBadge    = match($iPriority) { 'high', 'critical', 'urgent' => 'bg-danger', 'medium' => 'bg-warning text-dark', default => 'bg-secondary' };
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block"><?= e($inc['title'] ?? 'Incident') ?></strong>
                                                    <small class="text-muted"><?= e(mb_strimwidth((string)($inc['description'] ?? ''), 0, 50, '…')) ?></small>
                                                </td>
                                                <td><span class="small text-muted"><?= e(ucfirst($inc['category'] ?? 'General')) ?></span></td>
                                                <td><span class="badge <?= $pBadge ?>"><?= ucfirst(e($iPriority)) ?></span></td>
                                                <td><span class="badge <?= $sBadge ?>"><?= ucfirst(e($iStatus)) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Quick Actions + Operational Summary -->
            <div class="col-lg-4">

                <!-- Quick Admin Actions Grid -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Admin Quick Actions</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= url('views/admin/students/create/create.php') ?>" class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-person-plus fs-3 mb-1"></i>
                                    <span class="small fw-bold">Add Student</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/admin/houses/create/create.php') ?>" class="btn btn-outline-success w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-building fs-3 mb-1"></i>
                                    <span class="small fw-bold">Add House</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/admin/rooms/create/create.php') ?>" class="btn btn-outline-info w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-door-closed fs-3 mb-1"></i>
                                    <span class="small fw-bold">Add Room</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/admin/rooms/allocation/allocation.php') ?>" class="btn btn-outline-secondary w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-diagram-3 fs-3 mb-1"></i>
                                    <span class="small fw-bold">Allocate Rooms</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/admin/announcements/create/create.php') ?>" class="btn btn-outline-warning w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-megaphone fs-3 mb-1"></i>
                                    <span class="small fw-bold">Broadcast</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/reports/dashboard/dashboard.php') ?>" class="btn btn-outline-dark w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-bar-chart fs-3 mb-1"></i>
                                    <span class="small fw-bold">Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operational Snapshot -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2 text-success"></i>Operational Snapshot</h6>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                                <span class="text-muted small">Room Occupancy</span>
                                <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill"><?= $occupancyPct ?>%</span>
                            </li>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                                <span class="text-muted small">Free Rooms</span>
                                <span class="badge bg-success bg-opacity-15 text-success rounded-pill"><?= max(0, $roomCount - $allocationCount) ?></span>
                            </li>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                                <span class="text-muted small">Open Incidents</span>
                                <span class="badge bg-danger bg-opacity-15 text-danger rounded-pill"><?= $openIncidentCount ?></span>
                            </li>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                                <span class="text-muted small">Unread Notifications</span>
                                <span class="badge bg-info bg-opacity-15 text-info rounded-pill"><?= $unreadNotificationCount ?></span>
                            </li>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                                <span class="text-muted small">Attendance Events</span>
                                <span class="badge bg-warning bg-opacity-15 text-warning rounded-pill"><?= $attendanceCount ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Report Shortcuts -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-info"></i>Report Shortcuts</h6>
                    </div>
                    <div class="card-body p-3 d-grid gap-2">
                        <a href="<?= url('views/admin/attendance/reports/reports.php') ?>" class="btn btn-outline-primary btn-sm">Attendance Report</a>
                        <a href="<?= url('views/admin/visitors/reports/reports.php') ?>" class="btn btn-outline-info btn-sm">Visitor Report</a>
                        <a href="<?= url('views/admin/incidents/reports/reports.php') ?>" class="btn btn-outline-danger btn-sm">Incident Report</a>
                        <a href="<?= url('views/reports/dashboard/dashboard.php') ?>" class="btn btn-outline-dark btn-sm">All Reports &rarr;</a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    window.dashboardData = {
        labels: ['Students', 'Houses', 'Rooms', 'Incidents', 'Attendance', 'Allocations', 'Logs', 'Notifications'],
        values: [<?= $studentCount ?>, <?= $houseCount ?>, <?= $roomCount ?>, <?= $incidentCount ?>, <?= $attendanceCount ?>, <?= $allocationCount ?>, <?= $activityLogCount ?>, <?= $notificationCount ?>]
    };
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
