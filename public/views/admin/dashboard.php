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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

// Fallback requires for runtime resilience
if (!class_exists('App\\Services\\NotificationService', false)) {
    require_once APP_ROOT . '/app/services/NotificationService.php';
}
if (!class_exists('App\\Services\\StudentService', false)) {
    require_once APP_ROOT . '/app/services/StudentService.php';
}
if (!class_exists('App\\Services\\FirebaseService', false)) {
    require_once APP_ROOT . '/app/services/FirebaseService.php';
}

use App\Services\StudentService;
use App\Services\FirebaseService;
use App\Services\NotificationService;

$pageTitle = 'Admin Dashboard';

// Live counts — swap for cached/aggregated reads at scale.
$studentCount        = count(FirebaseService::getInstance()->getCollection(COL_STUDENTS, [], 1000));
$houseCount          = count(FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100));
$roomCount           = count(FirebaseService::getInstance()->getCollection(COL_ROOMS, [], 500));
$incidentCount       = count(FirebaseService::getInstance()->getCollection(COL_INCIDENTS, [], 500));
$attendanceCount     = count(FirebaseService::getInstance()->getCollection(COL_ATTENDANCE, [], 500));
$allocationCount     = count(FirebaseService::getInstance()->getCollection(COL_ROOM_ALLOCATIONS, [], 500));
$activityLogCount    = count(FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500));
$notificationCount   = count((new NotificationService())->all());
$openIncidentCount   = count(array_filter(FirebaseService::getInstance()->getCollection(COL_INCIDENTS, [], 500), static fn(array $incident): bool => ($incident['status'] ?? 'open') === 'open'));
$unreadNotificationCount = count(array_filter((new NotificationService())->all(), static fn(array $notification): bool => empty($notification['read'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/admin/visitors/index.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/reports/dashboard.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/admin/settings/index.php')],
];

$pageScripts = ['dashboard.js'];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper admin-dashboard-page">
        <section class="admin-dashboard-hero mb-4">
            <div class="admin-dashboard-icon"><i class="bi bi-grid-1x2"></i></div>
            <div><span class="admin-kicker">Administration center</span><h1>System overview</h1><p>Monitor residents, spaces, attendance, visitors, incidents, and system activity from one place.</p></div>
            <div class="admin-dashboard-actions"><a class="btn btn-light" href="<?= url('views/admin/profile.php') ?>"><i class="bi bi-person-circle me-1"></i>My profile</a><a class="btn btn-primary" href="<?= url('views/admin/settings/index.php') ?>"><i class="bi bi-gear me-1"></i>Settings</a></div>
        </section>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-mortarboard"></i></div>
                    <div><div class="text-muted small">Students</div><div class="fs-4 fw-bold"><span id="studentCount"><?= $studentCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-building"></i></div>
                    <div><div class="text-muted small">Houses</div><div class="fs-4 fw-bold"><span id="houseCount"><?= $houseCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-door-closed"></i></div>
                    <div><div class="text-muted small">Rooms</div><div class="fs-4 fw-bold"><span id="roomCount"><?= $roomCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    <div><div class="text-muted small">Incidents</div><div class="fs-4 fw-bold"><span id="incidentCount"><?= $incidentCount ?></span></div></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-event"></i></div>
                    <div><div class="text-muted small">Attendance Records</div><div class="fs-4 fw-bold"><span id="attendanceCount"><?= $attendanceCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-door-open"></i></div>
                    <div><div class="text-muted small">Room Allocations</div><div class="fs-4 fw-bold"><span id="allocationCount"><?= $allocationCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-purple bg-opacity-10 text-purple"><i class="bi bi-bar-chart-line"></i></div>
                    <div><div class="text-muted small">Activity Logs</div><div class="fs-4 fw-bold"><span id="activityLogCount"><?= $activityLogCount ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-bell"></i></div>
                    <div><div class="text-muted small">Notifications</div><div class="fs-4 fw-bold"><span id="notificationCount"><?= $notificationCount ?></span></div></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                        <div class="card stat-card admin-dashboard-panel p-3 h-100">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3">
                        <div>
                            <h6 class="mb-1">Usage Overview</h6>
                            <p class="text-muted mb-0">Live service totals and operational trends for your dormitory system.</p>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success">Updated just now</span>
                    </div>

                    <div class="chart-wrapper" style="min-height: 320px;">
                        <canvas id="dashboardChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                        <div class="card stat-card admin-dashboard-panel p-3 mb-3">
                    <h6 class="mb-3">Operational Snapshot</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                            <span>Room occupancy</span>
                            <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill"><?= $roomCount > 0 ? round(($allocationCount / $roomCount) * 100) . '%' : 'N/A' ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                            <span>Rooms available</span>
                            <span class="badge bg-success bg-opacity-15 text-success rounded-pill"><?= max(0, $roomCount - $allocationCount) ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                            <span>Daily attendance events</span>
                            <span class="badge bg-warning bg-opacity-15 text-warning rounded-pill"><?= $attendanceCount ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-0">
                            <span>Unread notifications</span>
                            <span class="badge bg-info bg-opacity-15 text-info rounded-pill"><?= $unreadNotificationCount ?></span>
                        </li>
                    </ul>
                </div>

                <div class="card stat-card admin-dashboard-panel p-3">
                    <h6 class="mb-3">Quick Insights</h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="alert alert-secondary py-2 mb-0" role="alert">
                            <strong><?= $openIncidentCount ?></strong> open incidents need review.
                        </div>
                        <div class="alert alert-secondary py-2 mb-0" role="alert">
                            <?= $houseCount ?> houses and <?= $roomCount ?> rooms are currently managed.
                        </div>
                        <div class="alert alert-secondary py-2 mb-0" role="alert">
                            <?= $activityLogCount ?> activity events were recorded recently.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stat-card admin-dashboard-panel p-3 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3">
                <div>
                    <h6 class="mb-1">House Staff Assignment</h6>
                    <p class="text-muted mb-0">Only House Master and House Mistress can be assigned to a house. Senior Houseparent is not included.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">House access</span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">House Master</span>
                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2">House Mistress</span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2">Senior Houseparent excluded</span>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('views/admin/users/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Assign House Staff
                </a>
                <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people me-1"></i> View assigned staff
                </a>
            </div>
        </div>

        <div class="card stat-card admin-dashboard-panel p-3 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="mb-1">Reporting Center</h6><p class="text-muted mb-0">Open detailed operational reports and exports.</p></div><i class="bi bi-bar-chart-line fs-3 text-primary"></i></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary btn-sm" href="<?= url('views/admin/attendance/reports.php') ?>">Attendance report</a><a class="btn btn-outline-primary btn-sm" href="<?= url('views/admin/visitors/reports.php') ?>">Visitor report</a><a class="btn btn-outline-primary btn-sm" href="<?= url('views/admin/incidents/reports.php') ?>">Incident report</a><a class="btn btn-outline-primary btn-sm" href="<?= url('views/reports/dashboard.php') ?>">All reports</a></div></div>

        <div class="card stat-card admin-dashboard-panel p-3">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3">
                <div>
                    <h6 class="mb-1">Quick Links</h6>
                    <p class="text-muted mb-0">Fast access to the most common admin actions.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">8 items</span>
            </div>

            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/students/create.php') ?>" class="btn btn-primary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-plus-lg fs-5"></i>
                        <span>Add Student</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/houses/create.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-building fs-5"></i>
                        <span>Add House</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/rooms/create.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-door-closed fs-5"></i>
                        <span>Add Room</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/rooms/allocation.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-diagram-3 fs-5"></i>
                        <span>Allocate Rooms</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-calendar-check fs-5"></i>
                        <span>Attendance</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/notifications/index.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-bell fs-5"></i>
                        <span>Notifications</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/admin/incidents/index.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-exclamation-triangle fs-5"></i>
                        <span>Incidents</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('views/reports/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-bar-chart fs-5"></i>
                        <span>Reports</span>
                    </a>
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
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
