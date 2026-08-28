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
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new IncidentService();
$incident = $service->find($id);
if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/admin/incidents/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = FirebaseService::getInstance()->deleteDocument(COL_INCIDENTS, $id);
    flash('success', 'Incident deleted successfully.');
    redirect(url('views/admin/incidents/index/index.php'));
}

$pageTitle = 'Delete Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:600px">
            <h5 class="mb-3">Delete Incident</h5>
            <p>Are you sure you want to delete the incident <strong>"<?= e($incident['title'] ?? 'Incident') ?>"</strong>? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger">Confirm delete</button>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/admin/incidents/index/index.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>