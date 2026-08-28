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

use App\Services\FirebaseService;
use App\Services\IncidentService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$incident = (new IncidentService())->find($id);

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/security/incidents/incidents/incidents.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->deleteDocument(COL_INCIDENTS, $id);
    flash('success', 'Incident deleted.');
    redirect(url('views/security/incidents/incidents/incidents.php'));
}

$pageTitle = 'Delete Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php'), 'active' => true],
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
                <span class="security-eyebrow"><i class="bi bi-trash3"></i> Incident management</span>
                <h1>Delete incident</h1>
                <p>Confirm the incident before removing it from the security records.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/incidents/view/view.php?id=' . urlencode($id)) ?>">
                <i class="bi bi-arrow-left"></i> Back to details
            </a>
        </section>

        <div class="security-card border-danger">
            <div class="security-card-header">
                <div>
                    <h2>Delete <?= e($incident['title'] ?? 'incident') ?>?</h2>
                    <p>This removes the incident entry from the system.</p>
                </div>
                <span class="badge bg-danger">Requires confirmation</span>
            </div>

            <dl class="row security-detail-list">
                <dt class="col-md-3">Title</dt>
                <dd class="col-md-9"><?= e($incident['title'] ?? 'Incident') ?></dd>
                <dt class="col-md-3">Priority</dt>
                <dd class="col-md-9"><?= e(ucfirst((string) ($incident['priority'] ?? 'medium'))) ?></dd>
                <dt class="col-md-3">Status</dt>
                <dd class="col-md-9"><?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?></dd>
            </dl>

            <form method="POST" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger" type="submit">
                    <i class="bi bi-trash3"></i> Confirm delete
                </button>
                <a class="btn btn-outline-secondary" href="<?= url('views/security/incidents/incidents/incidents.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
