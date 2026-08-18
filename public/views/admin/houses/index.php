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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\HouseService;

$pageTitle = 'Houses';
$houses = HouseService::all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Houses</h5>
            <a href="<?= url('views/admin/houses/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New House</a>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Capacity</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($houses as $house): ?>
                    <tr>
                        <td><?= e($house['name'] ?? '-') ?></td>
                        <td><?= e($house['gender'] ?? '-') ?></td>
                        <td><?= e($house['capacity'] ?? '-') ?></td>
                        <td><?= e($house['location'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($house['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($house['status'] ?? '') ?></span></td>
                        <td>
                            <a href="<?= url('views/admin/houses/view.php?id=' . urlencode($house['id'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="<?= url('views/admin/houses/edit.php?id=' . urlencode($house['id'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>