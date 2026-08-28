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
use App\Services\HouseService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$house = $id ? HouseService::find($id) : null;

if (!$house) {
    flash('error', 'House not found.');
    redirect(url('views/admin/houses/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    HouseService::delete($id);
    flash('success', 'House deleted successfully.');
    redirect(url('views/admin/houses/index/index.php'));
}

$pageTitle = 'Delete House';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-danger">
                    <i class="bi bi-building-dash me-2"></i>Delete House Record
                </h4>
                <p class="text-muted mb-0">Permanently remove dormitory facility from the system</p>
            </div>
            <div>
                <a href="<?= url('views/admin/houses/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Houses
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger" style="max-width: 600px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-trash3-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete <?= e($house['name'] ?? 'House') ?>?</h5>
                <p class="text-muted mb-3">
                    Campus Location: <strong class="text-dark"><?= e($house['location'] ?? 'Main Campus') ?></strong>
                </p>
                <div class="alert alert-danger text-start py-2 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Warning:</strong> Deleting this house will unassign all rooms, beds, and student residency associations currently linked to it.
                </div>
                <form method="POST" action="<?= url('views/admin/houses/delete/delete.php?id=' . urlencode($id)) ?>" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Permanently Delete
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= url('views/admin/houses/index/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>