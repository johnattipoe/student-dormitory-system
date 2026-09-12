<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 5) . '/public/bootstrap.php';
}
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$pageTitle = 'House Management Settings';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/house-master/settings/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div><h5 class="mb-1"><i class="bi bi-building-gear me-2 text-success"></i>House Management</h5><p class="text-muted mb-0">Manage operational preferences for your assigned house.</p></div>
            <a href="<?= url('views/house-master/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a>
        </div>
        <div class="row g-3" style="max-width: 900px;">
            <div class="col-md-6"><a href="<?= url('views/house-master/settings/index.php') ?>#notification-preferences" class="card stat-card h-100 p-4 text-decoration-none"><i class="bi bi-bell fs-3 text-primary mb-2"></i><h6 class="fw-bold text-dark">Alert Preferences</h6><p class="text-muted small mb-0">Configure curfew, roll call, emergency, incident, and visitor alerts.</p></a></div>
            <div class="col-md-6"><a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="card stat-card h-100 p-4 text-decoration-none"><i class="bi bi-calendar-check fs-3 text-success mb-2"></i><h6 class="fw-bold text-dark">Attendance Operations</h6><p class="text-muted small mb-0">Open attendance management for students in your assigned house.</p></a></div>
            <div class="col-md-6"><a href="<?= url('views/house-master/rooms/index/index.php') ?>" class="card stat-card h-100 p-4 text-decoration-none"><i class="bi bi-door-closed fs-3 text-info mb-2"></i><h6 class="fw-bold text-dark">Rooms and Beds</h6><p class="text-muted small mb-0">Review rooms, occupancy, and bed assignments.</p></a></div>
            <div class="col-md-6"><a href="<?= url('views/house-master/incidents/index/index.php') ?>" class="card stat-card h-100 p-4 text-decoration-none"><i class="bi bi-flag fs-3 text-danger mb-2"></i><h6 class="fw-bold text-dark">Incident Management</h6><p class="text-muted small mb-0">Review and follow up on incidents in your house.</p></a></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
