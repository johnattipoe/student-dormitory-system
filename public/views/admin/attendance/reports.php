<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\AttendanceService;

$pageTitle = 'Attendance Reports';
$summary = AttendanceService::summary(date('Y-m-d'));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Attendance Reports', 'href' => url('views/admin/attendance/reports.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Attendance Reports</h5>
            <p class="text-muted">Weekly and daily attendance metrics.</p>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6>Present</h6>
                    <p class="display-6 mb-0"><?= e($summary['present'] ?? 0) ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6>Absent</h6>
                    <p class="display-6 mb-0"><?= e($summary['absent'] ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>