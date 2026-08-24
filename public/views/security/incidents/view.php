<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;

$id = sanitize($_GET['id'] ?? '');
$incident = (new IncidentService())->find($id);

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/security/incidents/incidents.php'));
}

$priority = strtolower((string) ($incident['priority'] ?? 'medium'));
$status = strtolower((string) ($incident['status'] ?? 'open'));
$pageTitle = 'Incident Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Report Incident', 'href' => url('views/security/report-incident/report-incident.php')],
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
                <span class="security-eyebrow"><i class="bi bi-shield-exclamation"></i> Incident profile</span>
                <h1><?= e($incident['title'] ?? 'Incident') ?></h1>
                <p>Review the incident, priority, student link, and current response status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light" href="<?= url('views/security/incidents/incidents.php') ?>">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a class="btn btn-warning" href="<?= url('views/security/incidents/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="security-card">
                    <div class="security-card-header">
                        <div>
                            <h2>Incident report</h2>
                            <p>Detailed record for security review and follow-up.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge <?= $priority === 'high' ? 'bg-danger' : ($priority === 'low' ? 'bg-info text-dark' : 'bg-warning text-dark') ?>">
                                <?= e(ucfirst($priority ?: 'medium')) ?>
                            </span>
                            <span class="badge <?= $status === 'resolved' ? 'bg-success' : 'bg-primary' ?>">
                                <?= e(ucfirst($status ?: 'open')) ?>
                            </span>
                        </div>
                    </div>

                    <dl class="row security-detail-list">
                        <dt class="col-md-4">Student</dt>
                        <dd class="col-md-8"><?= e($incident['studentId'] ?? 'Not linked') ?></dd>

                        <dt class="col-md-4">Priority</dt>
                        <dd class="col-md-8"><?= e(ucfirst($priority ?: 'medium')) ?></dd>

                        <dt class="col-md-4">Status</dt>
                        <dd class="col-md-8"><?= e(ucfirst($status ?: 'open')) ?></dd>

                        <dt class="col-md-4">Description</dt>
                        <dd class="col-md-8"><?= nl2br(e($incident['description'] ?? 'No description recorded.')) ?></dd>

                        <dt class="col-md-4">Created</dt>
                        <dd class="col-md-8"><?= e($incident['createdAt'] ?? 'Not recorded') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="security-side-card">
                    <h3>Response actions</h3>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary" href="<?= url('views/security/incidents/edit.php?id=' . urlencode($id)) ?>">Update incident</a>
                        <a class="btn btn-outline-danger" href="<?= url('views/security/incidents/delete.php?id=' . urlencode($id)) ?>">Delete incident</a>
                    </div>
                </div>
                <div class="security-side-card mt-4">
                    <h3>Handling note</h3>
                    <p class="mb-0 text-muted">Keep status current so administrators can see which cases are open, under investigation, or resolved.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
