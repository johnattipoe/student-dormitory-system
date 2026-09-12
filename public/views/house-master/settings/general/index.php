<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 5) . '/public/bootstrap.php';
}
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$user = current_user() ?? [];
$pageTitle = 'General Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/house-master/settings/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div><h5 class="mb-1"><i class="bi bi-person-vcard me-2 text-primary"></i>General Profile</h5><p class="text-muted mb-0">Review your House Master account details.</p></div>
            <a href="<?= url('views/house-master/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a>
        </div>
        <div class="card stat-card p-4" style="max-width: 800px;">
            <dl class="row mb-0">
                <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= e($user['name'] ?? $user['fullName'] ?? 'Not provided') ?></dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($user['email'] ?? 'Not provided') ?></dd>
                <dt class="col-sm-4">Role</dt><dd class="col-sm-8">House Master</dd>
                <dt class="col-sm-4">Assigned House</dt><dd class="col-sm-8"><?= e($user['houseName'] ?? $user['houseId'] ?? 'Not assigned') ?></dd>
            </dl>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
