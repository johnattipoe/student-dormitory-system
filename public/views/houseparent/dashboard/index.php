<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Senior Houseparent Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5>Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
            <p class="text-muted">This is the Senior Houseparent dashboard scaffold. Follow the pattern in
               <code>public/views/admin/students/*.php</code> + <code>app/services/StudentService.php</code>
               to build out this role's screens (reuse the same service classes, just filter by
               <code>current_user()['houseId']</code> / <code>can('resource', 'need')</code> where relevant).</p>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
