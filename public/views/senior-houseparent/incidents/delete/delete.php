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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
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
    redirect(url('views/senior-houseparent/incidents/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->deleteDocument(COL_INCIDENTS, $id);
    flash('success', 'Incident deleted successfully.');
    redirect(url('views/senior-houseparent/incidents/index/index.php'));
}

$pageTitle = 'Delete Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:600px">
            <h5 class="mb-3">Delete Incident</h5>
            <p>Delete incident <strong>"<?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?>"</strong>? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger"><i class="bi bi-trash me-1"></i> Confirm Delete</button>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>