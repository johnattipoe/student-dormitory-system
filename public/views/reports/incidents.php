<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ReportService;

$pageTitle = 'Incident Report';
$report = (new ReportService())->incidents();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incident Report', 'href' => url('views/reports/incidents.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Incident Report</h5>
        <?php $reportType = 'incidents'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Total</div><div class="fs-3 fw-bold"><?= e((string) ($report['total'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Open</div><div class="fs-3 fw-bold"><?= e((string) ($report['open'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Resolved</div><div class="fs-3 fw-bold"><?= e((string) ($report['resolved'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">High</div><div class="fs-3 fw-bold"><?= e((string) ($report['high'] ?? 0)) ?></div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
