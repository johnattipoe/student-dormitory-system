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

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\VisitorService;

$visitors = (new VisitorService())->all();
$incidents = (new IncidentService())->all();
$inside = count(array_filter($visitors, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));
$open = count(array_filter($incidents, fn($incident) => ($incident['status'] ?? 'open') === 'open'));
$resolved = count(array_filter($incidents, fn($incident) => ($incident['status'] ?? '') === 'resolved'));

$pageTitle = 'Security Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/security/reports/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Security Reporting Center</h4>
                <p class="text-muted mb-0">Download visitor activity, incident reports, and review operational security statistics</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url('reports/export.php?type=visitors&format=csv') ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Visitor CSV
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= url('reports/export.php?type=incidents&format=pdf') ?>">
                    <i class="bi bi-filetype-pdf me-1"></i>Incident PDF
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($visitors)) ?></h3>
                            <span class="small text-muted">All registered entries</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently Inside</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $inside) ?></h3>
                            <span class="small text-muted">Active on campus</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Incidents</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($incidents)) ?></h3>
                            <span class="small text-muted">Recorded events</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $open) ?></h3>
                            <span class="small text-muted">Pending action</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-hourglass-split fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-download me-2 text-primary"></i>Export Data Reports</h6>
                        <small class="text-muted">Export data for administration, audit, or end-of-day handover</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card p-3 border h-100 bg-light">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary">
                                            <i class="bi bi-people fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold mb-0">Visitor Report</h6>
                                    </div>
                                    <p class="text-muted small mb-3">All registered visitors, check-in, and check-out logs.</p>
                                    <div class="d-flex gap-2 mt-auto">
                                        <a class="btn btn-outline-success btn-sm flex-fill" href="<?= url('reports/export.php?type=visitors&format=csv') ?>">
                                            <i class="bi bi-filetype-csv me-1"></i>CSV
                                        </a>
                                        <a class="btn btn-outline-danger btn-sm flex-fill" href="<?= url('reports/export.php?type=visitors&format=pdf') ?>">
                                            <i class="bi bi-filetype-pdf me-1"></i>PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card p-3 border h-100 bg-light">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger">
                                            <i class="bi bi-exclamation-triangle fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold mb-0">Incident Report</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Incident history, investigation status, and priority logs.</p>
                                    <div class="d-flex gap-2 mt-auto">
                                        <a class="btn btn-outline-success btn-sm flex-fill" href="<?= url('reports/export.php?type=incidents&format=csv') ?>">
                                            <i class="bi bi-filetype-csv me-1"></i>CSV
                                        </a>
                                        <a class="btn btn-outline-danger btn-sm flex-fill" href="<?= url('reports/export.php?type=incidents&format=pdf') ?>">
                                            <i class="bi bi-filetype-pdf me-1"></i>PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2 text-primary"></i>Operational Status Breakdown</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-exclamation-circle text-warning me-2"></i>Open Incidents</span>
                                <span class="badge bg-warning text-dark"><?= e((string) $open) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-check-circle text-success me-2"></i>Resolved Incidents</span>
                                <span class="badge bg-success"><?= e((string) $resolved) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-shield-check text-primary me-2"></i>Total Incidents</span>
                                <span class="badge bg-primary"><?= e((string) count($incidents)) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-person-walking text-info me-2"></i>Active Visitors on Campus</span>
                                <span class="badge bg-info"><?= e((string) $inside) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
