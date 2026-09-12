<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 5) . '/public/bootstrap.php';
}
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$pageTitle = 'Security Settings';
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
            <div><h5 class="mb-1"><i class="bi bi-shield-lock me-2 text-danger"></i>Security Settings</h5><p class="text-muted mb-0">Manage account access and security actions.</p></div>
            <a href="<?= url('views/house-master/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a>
        </div>
        <div class="card stat-card p-4" style="max-width: 800px;">
            <h6 class="fw-bold">Account Security</h6>
            <p class="text-muted">Use the password recovery process to set a new password. You will need a valid reset link.</p>
            <a href="<?= url('forgot-password.php') ?>" class="btn btn-outline-danger align-self-start"><i class="bi bi-key me-1"></i>Request Password Reset</a>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
