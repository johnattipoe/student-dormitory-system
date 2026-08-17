<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ReportService;

$pageTitle = 'Occupancy Report';
$report = (new ReportService())->occupancy();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Occupancy Report', 'href' => url('views/reports/occupancy.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Room Occupancy</h5>
        <?php $reportType = 'occupancy'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Rooms</div><div class="fs-3 fw-bold"><?= e((string) ($report['rooms'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Total Capacity</div><div class="fs-3 fw-bold"><?= e((string) ($report['capacity'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Occupied</div><div class="fs-3 fw-bold"><?= e((string) ($report['occupied'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Rate</div><div class="fs-3 fw-bold"><?= e((string) ($report['occupancyRate'] ?? 0)) ?>%</div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
