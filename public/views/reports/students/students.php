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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\ReportService;

$pageTitle = 'Student Report';
$report = (new ReportService())->students();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Student Report', 'href' => url('views/reports/students/students.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Student Report</h5>
        <?php $reportType = 'students'; require APP_ROOT . '/app/views/components/report-downloads/report-downloads.php'; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Total</div><div class="fs-3 fw-bold"><?= e((string) ($report['total'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Active</div><div class="fs-3 fw-bold"><?= e((string) ($report['active'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Inactive</div><div class="fs-3 fw-bold"><?= e((string) ($report['inactive'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Male</div><div class="fs-3 fw-bold"><?= e((string) ($report['male'] ?? 0)) ?></div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
