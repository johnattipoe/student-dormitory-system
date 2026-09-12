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
use App\Services\AttendanceService;

$pageTitle = 'Attendance Reports';
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$summary = AttendanceService::summary($date);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Attendance Reports', 'href' => url('views/admin/attendance/reports/reports.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Attendance Reports</h5>
            <p class="text-muted">Daily attendance metrics and export-ready summaries.</p>
            <form method="GET" class="d-flex gap-2 mb-3"><input type="date" name="date" class="form-control" value="<?= e($date) ?>"><button class="btn btn-primary btn-sm">View</button><a class="btn btn-success btn-sm" href="<?= url('reports/export.php?type=attendance&format=csv') ?>">CSV</a></form>
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
            <div class="col-md-6"><div class="card stat-card p-3"><h6>Late</h6><p class="display-6 mb-0"><?= e($summary['late'] ?? 0) ?></p></div></div>
            <div class="col-md-6"><div class="card stat-card p-3"><h6>Excused</h6><p class="display-6 mb-0"><?= e($summary['excused'] ?? 0) ?></p></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>