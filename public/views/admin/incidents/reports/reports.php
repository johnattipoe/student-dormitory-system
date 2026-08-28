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
use App\Services\IncidentService;

$pageTitle = 'Incident Reports';
$incidentService = new IncidentService();
$allIncidents = $incidentService->all();
$openCount = $incidentService->openCount();
$resolvedCount = count(array_filter($allIncidents, fn($i) => ($i['status'] ?? '') !== 'open'));
$highPriorityCount = count(array_filter($allIncidents, fn($i) => in_array(strtolower((string)($i['priority'] ?? '')), ['high', 'critical', 'urgent'], true)));
$totalCount = count($allIncidents);

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index/index.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/incidents/reports/reports.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-bar-chart-fill text-danger me-2"></i>Campus Incident Analytics &amp; Reports
                </h4>
                <p class="text-muted mb-0">Aggregate summary of disciplinary proceedings, safety escalations, and resolution trends</p>
            </div>
            <div>
                <a href="<?= url('views/admin/incidents/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Incident Log
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Logged</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCount) ?></h3>
                            <span class="small text-muted">All-time occurrences</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $openCount) ?></h3>
                            <span class="small text-muted"><?= $totalCount > 0 ? round(($openCount / $totalCount) * 100) : 0 ?>% unaddressed</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent Escalations</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $highPriorityCount) ?></h3>
                            <span class="small text-muted">High priority cases</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-shield-exclamation fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Resolved Cases</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $resolvedCount) ?></h3>
                            <span class="small text-muted"><?= $totalCount > 0 ? round(($resolvedCount / $totalCount) * 100) : 0 ?>% resolution rate</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>