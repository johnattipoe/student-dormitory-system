<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ReportService;

$pageTitle = 'Reports Dashboard';
$reportService = new ReportService();
$summary = $reportService->dashboard();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/reports/dashboard.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Operational Reports</h5>
        <?php $reportType = 'dashboard'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Students</div>
                    <div class="fs-3 fw-bold"><?= e((string) ($summary['students'] ?? 0)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Rooms</div>
                    <div class="fs-3 fw-bold"><?= e((string) ($summary['rooms'] ?? 0)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Occupancy</div>
                    <div class="fs-3 fw-bold"><?= e((string) (($summary['occupancy']['occupancyRate'] ?? 0))) ?>%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Visitors</div>
                    <div class="fs-3 fw-bold"><?= e((string) (($summary['visitors']['total'] ?? 0))) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3 mt-4">
            <table class="table table-hover w-100">
                <thead>
                <tr>
                    <th>Section</th>
                    <th>Value</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>Attendance</td><td><?= e((string) (($summary['attendance']['present'] ?? 0))) ?> present</td></tr>
                <tr><td>Open incidents</td><td><?= e((string) (($summary['incidents']['open'] ?? 0))) ?></td></tr>
                <tr><td>Medical cases</td><td><?= e((string) (($summary['medical']['total'] ?? 0))) ?></td></tr>
                <tr><td>Rooms occupied</td><td><?= e((string) (($summary['occupancy']['occupied'] ?? 0))) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
