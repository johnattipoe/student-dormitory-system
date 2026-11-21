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

$id = sanitize($_GET['id'] ?? '');
$incident = (new IncidentService())->find($id);

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/security/incidents/incidents/incidents.php'));
}

$priority = strtolower((string) ($incident['priority'] ?? 'medium'));
$status = strtolower((string) ($incident['status'] ?? 'open'));
$pageTitle = 'Incident Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Report Incident', 'href' => url('views/security/report-incident/report-incident.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div>
                <span class="security-eyebrow"><i class="bi bi-shield-exclamation"></i> Incident profile</span>
                <h1><?= e($incident['title'] ?? 'Incident') ?></h1>
                <p>Review the incident, priority, student link, and current response status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light" href="<?= url('views/security/incidents/incidents/incidents.php') ?>">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#securityIncidentDetailEdit">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
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
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#securityIncidentDetailEdit">Update incident</button>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#securityIncidentDetailDelete">Delete incident</button>
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
<div class="modal fade" id="securityIncidentDetailEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/security/incidents/edit/edit.php?id=' . urlencode($id)) ?>" class="security-modal-form">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <input type="hidden" name="returnToIncidentView" value="1">
                <div class="modal-header"><h5 class="modal-title">Edit Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($incident['title'] ?? '') ?>" required>
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select mb-3"><?php foreach (['low', 'medium', 'high'] as $option): ?><option value="<?= e($option) ?>" <?= $priority === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select mb-3"><?php foreach (['open', 'investigating', 'resolved'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select>
                    <label class="form-label">Description</label><textarea name="description" class="form-control" rows="5"><?= e($incident['description'] ?? '') ?></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="securityIncidentDetailDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger">Delete Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Delete <strong><?= e($incident['title'] ?? 'this incident') ?></strong> from the incident records?</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/security/incidents/delete/delete.php?id=' . urlencode($id)) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
