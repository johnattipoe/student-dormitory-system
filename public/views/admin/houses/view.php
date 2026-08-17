<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\HouseService;

$pageTitle = 'View House';
$id = $_GET['id'] ?? null;
$house = $id ? HouseService::find($id) : null;
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index.php')],
    ['icon' => 'bi-eye', 'label' => 'View House', 'href' => url('views/admin/houses/view.php?id=' . urlencode($id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <?php if (!$house): ?>
            <div class="alert alert-warning">House not found.</div>
        <?php else: ?>
            <div class="card stat-card p-4" style="max-width:720px;">
                <h5 class="mb-3">House Details</h5>
                <dl class="row">
                    <?php foreach (['Name' => 'name', 'Gender' => 'gender', 'Capacity' => 'capacity', 'Location' => 'location', 'Status' => 'status'] as $label => $key): ?>
                        <dt class="col-sm-4 text-muted"><?= e($label) ?></dt>
                        <dd class="col-sm-8"><?= e($house[$key] ?? '-') ?></dd>
                    <?php endforeach; ?>
                </dl>
                <a href="<?= url('views/admin/houses/index.php') ?>" class="btn btn-outline-secondary">Back to houses</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>