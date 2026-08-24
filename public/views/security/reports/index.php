<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div>
                <span class="security-eyebrow"><i class="bi bi-file-earmark-bar-graph"></i> Reporting center</span>
                <h1>Security reports</h1>
                <p>Download visitor and incident reports, and review the current security summary.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light" href="<?= url('reports/export.php?type=visitors&format=csv') ?>"><i class="bi bi-download"></i> Visitor CSV</a>
                <a class="btn btn-warning" href="<?= url('reports/export.php?type=incidents&format=pdf') ?>"><i class="bi bi-filetype-pdf"></i> Incident PDF</a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="security-stat"><span>Total visitors</span><strong><?= e((string) count($visitors)) ?></strong></div></div>
            <div class="col-md-3"><div class="security-stat"><span>Currently inside</span><strong><?= e((string) $inside) ?></strong></div></div>
            <div class="col-md-3"><div class="security-stat"><span>Total incidents</span><strong><?= e((string) count($incidents)) ?></strong></div></div>
            <div class="col-md-3"><div class="security-stat"><span>Open incidents</span><strong><?= e((string) $open) ?></strong></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="security-card h-100">
                    <div class="security-card-header">
                        <div>
                            <h2>Download reports</h2>
                            <p>Export data for administration, audit, or end-of-day handover.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="security-report-tile">
                                <i class="bi bi-people"></i>
                                <h3>Visitor report</h3>
                                <p>All registered visitors, check-in, and check-out details.</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-success btn-sm" href="<?= url('reports/export.php?type=visitors&format=csv') ?>">CSV</a>
                                    <a class="btn btn-danger btn-sm" href="<?= url('reports/export.php?type=visitors&format=pdf') ?>">PDF</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="security-report-tile">
                                <i class="bi bi-exclamation-triangle"></i>
                                <h3>Incident report</h3>
                                <p>Incident history, status, and priority records.</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-success btn-sm" href="<?= url('reports/export.php?type=incidents&format=csv') ?>">CSV</a>
                                    <a class="btn btn-danger btn-sm" href="<?= url('reports/export.php?type=incidents&format=pdf') ?>">PDF</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <aside class="security-side-card h-100">
                    <h3>Incident status</h3>
                    <ul class="security-info-list">
                        <li><span>Open</span><strong><?= e((string) $open) ?></strong></li>
                        <li><span>Resolved</span><strong><?= e((string) $resolved) ?></strong></li>
                        <li><span>Total</span><strong><?= e((string) count($incidents)) ?></strong></li>
                    </ul>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
