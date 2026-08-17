<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ReportService;

$pageTitle = 'Visitor Report';
$report = (new ReportService())->visitors();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitor Report', 'href' => url('views/reports/visitors.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Visitor Report</h5>
        <?php $reportType = 'visitors'; require APP_ROOT . '/app/views/components/report-downloads.php'; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Total</div><div class="fs-3 fw-bold"><?= e((string) ($report['total'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Today</div><div class="fs-3 fw-bold"><?= e((string) ($report['today'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Inside</div><div class="fs-3 fw-bold"><?= e((string) ($report['inside'] ?? 0)) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Pending</div><div class="fs-3 fw-bold"><?= e((string) ($report['pending'] ?? 0)) ?></div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
