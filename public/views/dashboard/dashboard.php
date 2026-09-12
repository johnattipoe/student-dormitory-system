<?php
if (!defined('APP_ROOT')) {
    require __DIR__ . '/../../bootstrap.php';
}

$role = \App\Services\AuthService::role();
if (!$role) {
    redirect(base_url('login.php'));
    exit;
}

if (isset(ROLE_DASHBOARD[$role])) {
    redirect(base_url('index.php?route=' . urlencode(ROLE_DASHBOARD[$role])));
    exit;
}

$user = current_user() ?? [];
$roleLabel = ucwords(str_replace(['_', '-'], ' ', $role));
$dashboardModules = [
    'users' => ['label' => 'Users', 'icon' => 'bi-people', 'href' => 'views/admin/users/index/index.php', 'level' => 'view'],
    'students' => ['label' => 'Students', 'icon' => 'bi-people', 'href' => 'views/admin/students/index/index.php', 'level' => 'view'],
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
$navItems = [['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/dashboard/dashboard.php'), 'active' => true]];
foreach ($dashboardModules as $moduleKey => $module) {
    if (can($moduleKey, $module['level'])) {
        $navItems[] = ['icon' => $module['icon'], 'label' => $module['label'], 'href' => url($module['href'])];
    }
}
$pageTitle = $roleLabel . ' Dashboard';
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><?= e($roleLabel) ?> Dashboard</h4>
                <p class="text-muted mb-0">Welcome, <?= e(current_user_name()) ?>. Your dashboard reflects your assigned permissions.</p>
            </div>
            <span class="badge bg-primary"><?= e($roleLabel) ?></span>
        </div>
        <div class="row g-3">
            <?php foreach ($dashboardModules as $moduleKey => $module): ?>
                <?php if (!can($moduleKey, $module['level'])): continue; endif; ?>
                <div class="col-sm-6 col-lg-4">
                    <a href="<?= url($module['href']) ?>" class="card stat-card h-100 p-4 text-decoration-none">
                        <i class="bi <?= e($module['icon']) ?> fs-2 text-primary mb-3"></i>
                        <h5 class="text-dark"><?= e($module['label']) ?></h5>
                        <span class="text-muted">Open <?= strtolower(e($module['label'])) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
