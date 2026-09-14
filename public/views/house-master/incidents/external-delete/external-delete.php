<?php
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

$fileName = sanitize($_GET['file'] ?? '');
$filePath = $fileName !== '' ? APP_ROOT . '/public/uploads/external-incidents/' . basename($fileName) : '';
$metaPath = $filePath !== '' ? $filePath . '.json' : '';

if ($fileName === '' || !file_exists($filePath)) {
    flash('error', 'External incident record not found.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @unlink($filePath);
    if (file_exists($metaPath)) {
        @unlink($metaPath);
    }
    flash('success', 'External incident record deleted successfully.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

$pageTitle = 'Delete External Incident';
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

        <div class="card stat-card shadow-sm border-0 mx-auto" style="max-width: 560px;">
            <div class="card-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-1 mb-3"></i>
                <h5 class="fw-bold mb-2">Delete External Incident Record?</h5>
                <p class="text-muted mb-4">This will remove the uploaded document and its record metadata from the system.</p>

                <form method="POST">
                    <div class="d-flex justify-content-center gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
