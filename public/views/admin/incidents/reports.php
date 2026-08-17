<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\IncidentService;

$pageTitle = 'Incident Reports';
$incidentService = new IncidentService();
$allIncidents = $incidentService->all();
$openCount = $incidentService->openCount();
$resolvedCount = count(array_filter($allIncidents, fn($i) => ($i['status'] ?? '') !== 'open'));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/incidents/reports.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Incident Reports</h5>
            <p class="text-muted">Overview of current incident statuses.</p>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Open Incidents</div>
                    <div class="fs-2 fw-bold"><?= e($openCount) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Resolved / Closed</div>
                    <div class="fs-2 fw-bold"><?= e($resolvedCount) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Total Incidents</div>
                    <div class="fs-2 fw-bold"><?= e(count($allIncidents)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>