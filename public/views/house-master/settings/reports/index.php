<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 5) . '/public/bootstrap.php';
}
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$pageTitle = 'Report Settings';
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
            <div><h5 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-info"></i>Report Settings</h5><p class="text-muted mb-0">Open the reports available for your assigned house.</p></div>
            <a href="<?= url('views/house-master/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a>
        </div>
        <div class="card stat-card p-4" style="max-width: 800px;">
            <h6 class="fw-bold">House Reports</h6>
            <p class="text-muted">Generate attendance, student, visitor, room, and incident reports from the House Master reports workspace.</p>
            <a href="<?= url('views/house-master/reports/index/index.php') ?>" class="btn btn-outline-info align-self-start"><i class="bi bi-bar-chart me-1"></i>Open House Reports</a>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
