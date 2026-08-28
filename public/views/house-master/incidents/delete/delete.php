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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$service = new IncidentService();
$incident = null;
foreach ($service->byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $incident = $record;
        break;
    }
}

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->deleteDocument(COL_INCIDENTS, $id);
    flash('success', 'Incident deleted successfully.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

$pageTitle = 'Delete Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-trash text-danger me-2"></i>Delete Incident</h4>
                <p class="text-muted mb-0">Confirm removal of house incident record</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Confirmation Card -->
        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger mx-auto" style="max-width: 540px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete this incident?</h5>
                <p class="text-muted mb-3">
                    Are you sure you want to permanently delete <strong>"<?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?>"</strong>?
                </p>
                <div class="alert alert-danger small text-start mb-4">
                    <i class="bi bi-info-circle me-1"></i> This action cannot be undone. Disciplinary and audit trails associated with this incident ID will be removed.
                </div>
                <form method="POST" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>