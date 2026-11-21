<?php
if (!defined('APP_ROOT')) {
    require __DIR__ . '/../../bootstrap.php';
}

use App\Services\AttendanceService;
use App\Services\FirebaseService;
use App\Services\IncidentService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;

$role = \App\Services\AuthService::role();
if (!$role) {
    redirect(base_url('login.php'));
    exit;
}

// Established roles have their own dedicated dashboards. This overview is for
// custom roles whose access is assembled from the permission matrix.
if (isset(ROLE_DASHBOARD[$role])) {
    redirect(base_url('index.php?route=' . urlencode(ROLE_DASHBOARD[$role])));
    exit;
}

$user = current_user() ?? [];
$roleLabel = ucwords(str_replace(['_', '-'], ' ', $role));
$dashboardModules = [
    'users' => ['label' => 'Users', 'icon' => 'bi-people', 'href' => 'views/admin/users/index/index.php', 'level' => 'view'],
    'students' => ['label' => 'Students', 'icon' => 'bi-mortarboard', 'href' => 'views/admin/students/index/index.php', 'level' => 'view'],
    'houses' => ['label' => 'Houses', 'icon' => 'bi-house', 'href' => 'views/admin/houses/index/index.php', 'level' => 'view'],
    'rooms' => ['label' => 'Rooms', 'icon' => 'bi-door-open', 'href' => 'views/admin/rooms/index/index.php', 'level' => 'view'],
    'room_allocation' => ['label' => 'Room Allocation', 'icon' => 'bi-grid-3x3-gap', 'href' => 'views/admin/rooms/allocation/allocation.php', 'level' => 'view'],
    'attendance' => ['label' => 'Attendance', 'icon' => 'bi-calendar-check', 'href' => 'views/attendance/index/index.php', 'level' => 'view'],
    'visitors' => ['label' => 'Visitors', 'icon' => 'bi-person-badge', 'href' => 'views/visitors/index/index.php', 'level' => 'view'],
    'visitor_requests' => ['label' => 'Visitor Requests', 'icon' => 'bi-person-check', 'href' => 'views/admin/visitors/index/index.php', 'level' => 'view'],
    'incidents' => ['label' => 'Incidents', 'icon' => 'bi-exclamation-triangle', 'href' => 'views/incidents/index/index.php', 'level' => 'view'],
    'reports' => ['label' => 'Reports', 'icon' => 'bi-file-earmark-text', 'href' => 'views/reports/dashboard/dashboard.php', 'level' => 'view'],
    'medical_records' => ['label' => 'Medical Records', 'icon' => 'bi-heart-pulse', 'href' => 'views/medical/index/index.php', 'level' => 'view'],
    'notifications' => ['label' => 'Notifications', 'icon' => 'bi-bell', 'href' => 'views/admin/notifications/index/index.php', 'level' => 'view'],
    'announcements' => ['label' => 'Announcements', 'icon' => 'bi-megaphone', 'href' => 'views/admin/announcements/index.php', 'level' => 'view'],
    'message_parents' => ['label' => 'Message Parents', 'icon' => 'bi-chat-left-text', 'href' => 'views/parent-messages/create/create.php', 'level' => 'view'],
    'emergency_alerts' => ['label' => 'Emergency Alerts', 'icon' => 'bi-exclamation-triangle', 'href' => 'views/admin/emergency-contacts/index.php', 'level' => 'view'],
    'emergency_contacts' => ['label' => 'Emergency Contacts', 'icon' => 'bi-telephone-inbound', 'href' => 'views/admin/emergency-contacts/index.php', 'level' => 'view'],
    'health_reports' => ['label' => 'Health Reports', 'icon' => 'bi-bar-chart', 'href' => 'views/medical/index/index.php', 'level' => 'view'],
    'activity_logs' => ['label' => 'Activity Logs', 'icon' => 'bi-clock-history', 'href' => 'views/admin/activity-logs/index.php', 'level' => 'view'],
    'audit_trail' => ['label' => 'Audit Trail', 'icon' => 'bi-list-check', 'href' => 'views/admin/activity-logs/index.php', 'level' => 'view'],
    'backup_restore' => ['label' => 'Backup & Restore', 'icon' => 'bi-database-down', 'href' => 'views/admin/backup-restore/index.php', 'level' => 'view'],
    'settings' => ['label' => 'Settings', 'icon' => 'bi-gear', 'href' => 'views/admin/settings/index/index.php', 'level' => 'view'],
    'profile' => ['label' => 'Profile', 'icon' => 'bi-person-circle', 'href' => 'views/admin/profile.php', 'level' => 'view'],
];

$accessibleModules = [];
$navItems = [['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/dashboard/dashboard.php'), 'active' => true]];
foreach ($dashboardModules as $moduleKey => $module) {
    if (!can($moduleKey, $module['level'])) {
        continue;
    }
    $accessibleModules[$moduleKey] = $module;
    $navItems[] = ['icon' => $module['icon'], 'label' => $module['label'], 'href' => url($module['href'])];
}

// Query only metrics the current custom role is permitted to view. Each metric
// is isolated so a temporarily unavailable Firestore collection won't blank out
// the rest of the dashboard.
$metricDefinitions = [
    'students' => ['label' => 'Students', 'icon' => 'bi-mortarboard', 'tone' => 'primary', 'href' => 'students', 'load' => static fn (): int => StudentService::count()],
    'rooms' => ['label' => 'Rooms', 'icon' => 'bi-door-open', 'tone' => 'info', 'href' => 'rooms', 'load' => static fn (): int => RoomService::count()],
    'attendance' => ['label' => 'Attendance today', 'icon' => 'bi-calendar-check', 'tone' => 'success', 'href' => 'attendance', 'load' => static function (): int { $data = AttendanceService::report(); return (int) ($data['total'] ?? 0); }],
    'visitors' => ['label' => 'Visitors today', 'icon' => 'bi-person-badge', 'tone' => 'warning', 'href' => 'visitors', 'load' => static fn (): int => (new VisitorService())->todayCount()],
    'incidents' => ['label' => 'Open incidents', 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger', 'href' => 'incidents', 'load' => static fn (): int => (new IncidentService())->openCount()],
];
$metrics = [];
$metricPermissionMap = ['students' => 'students', 'rooms' => 'rooms', 'attendance' => 'attendance', 'visitors' => 'visitors', 'incidents' => 'incidents'];
foreach ($metricDefinitions as $metricKey => $definition) {
    $permissionKey = $metricPermissionMap[$metricKey];
    if (!isset($accessibleModules[$permissionKey])) {
        continue;
    }
    try {
        $definition['value'] = ($definition['load'])();
        $definition['url'] = url($accessibleModules[$permissionKey]['href']);
        $metrics[] = $definition;
    } catch (Throwable $exception) {
        error_log('Dashboard metric unavailable (' . $metricKey . '): ' . $exception->getMessage());
    }
}

$recentActivity = [];
if (isset($accessibleModules['activity_logs']) || isset($accessibleModules['audit_trail'])) {
    try {
        $recentActivity = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 6, 0, false);
        usort($recentActivity, static fn (array $a, array $b): int => strcmp((string) ($b['createdAt'] ?? $b['timestamp'] ?? ''), (string) ($a['createdAt'] ?? $a['timestamp'] ?? '')));
    } catch (Throwable $exception) {
        error_log('Dashboard activity feed unavailable: ' . $exception->getMessage());
    }
}

$pageTitle = $roleLabel . ' Dashboard';
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="permission-dashboard">
        <section class="dashboard-welcome card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-body p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge rounded-pill text-bg-primary-subtle text-primary mb-2">Workspace overview</span>
                    <h1 class="h3 fw-bold mb-2">Welcome, <?= e(current_user_name()) ?></h1>
                    <p class="text-muted mb-0">Your <?= e(strtolower($roleLabel)) ?> workspace. Your access is based on the permissions assigned to your account.</p>
                </div>
                <div class="text-md-end">
                    <div class="small text-muted">Signed in as</div>
                    <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= e($roleLabel) ?></span>
                </div>
            </div>
        </section>

        <?php if ($metrics): ?>
            <div class="row g-3 mb-4">
                <?php foreach ($metrics as $metric): ?>
                    <div class="col-sm-6 col-xl-3">
                        <a class="card stat-card h-100 p-3 text-decoration-none border-0 shadow-sm" href="<?= e($metric['url']) ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="small text-muted text-uppercase fw-semibold"><?= e($metric['label']) ?></span>
                                    <div class="fs-2 fw-bold text-dark mt-1"><?= e(number_format((int) $metric['value'])) ?></div>
                                    <span class="small text-primary">Open details <i class="bi bi-arrow-up-right ms-1"></i></span>
                                </div>
                                <span class="rounded-3 bg-<?= e($metric['tone']) ?> bg-opacity-10 text-<?= e($metric['tone']) ?> p-3"><i class="bi <?= e($metric['icon']) ?> fs-4"></i></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($metrics): ?>
            <div class="row g-4 mb-4">
                <div class="col-xl-8">
                    <section class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <div><h2 class="h6 fw-bold mb-1">Operational snapshot</h2><p class="small text-muted mb-0">Live totals for modules available to your role.</p></div>
                            <span class="badge rounded-pill text-bg-success">Live data</span>
                        </div>
                        <div class="card-body"><div style="height:260px"><canvas id="permissionDashboardChart" role="img" aria-label="Chart of available dormitory system metrics"></canvas></div></div>
                    </section>
                </div>
                <div class="col-xl-4">
                    <section class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3"><h2 class="h6 fw-bold mb-1">Quick access</h2><p class="small text-muted mb-0">Jump straight to your available tools.</p></div>
                        <div class="card-body pt-0 d-grid gap-2">
                            <?php foreach (array_slice($accessibleModules, 0, 5, true) as $quickModule): ?>
                                <a href="<?= url($quickModule['href']) ?>" class="btn btn-light text-start d-flex align-items-center gap-2"><i class="bi <?= e($quickModule['icon']) ?> text-primary"></i><span><?= e($quickModule['label']) ?></span><i class="bi bi-arrow-up-right ms-auto text-muted"></i></a>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="SDS.showButtonLoading(this); window.location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh dashboard data</button>
                        </div>
                    </section>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('permissionDashboardChart');
                const chartData = <?= json_encode(['labels' => array_column($metrics, 'label'), 'values' => array_map(static fn (array $metric): int => (int) $metric['value'], $metrics)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                if (!canvas || typeof Chart === 'undefined') return;
                new Chart(canvas, {
                    type: 'bar',
                    data: { labels: chartData.labels, datasets: [{ label: 'Current total', data: chartData.values, backgroundColor: ['#4f46e5','#0891b2','#059669','#d97706','#dc2626'], borderRadius: 7, maxBarThickness: 46 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } }
                });
            });
            </script>
        <?php endif; ?>

        <div class="row g-4">
            <?php
            $renderWorkspace = require __DIR__ . '/components/workspace/index.php';
            $renderWorkspace($accessibleModules);
            ?>
            <?php
            $renderActivity = require __DIR__ . '/components/activity/index.php';
            $renderActivity($accessibleModules, $recentActivity);
            ?>
        </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
