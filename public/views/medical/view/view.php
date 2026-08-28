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
$allowedRoles = [ROLE_ADMIN, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = $_GET['id'] ?? '';
$record = $id ? FirebaseService::getInstance()->getDocument('medical_records', $id) : null;
$pageTitle = 'Medical Record Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => url('views/medical/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Medical Record Details</h5>
            <?php if ($record): ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Student</dt><dd class="col-sm-9"><?= e($record['studentId'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Diagnosis</dt><dd class="col-sm-9"><?= e($record['diagnosis'] ?? '') ?></dd>
                    <dt class="col-sm-3">Severity</dt><dd class="col-sm-9"><?= e($record['severity'] ?? '') ?></dd>
                    <dt class="col-sm-3">Treatment</dt><dd class="col-sm-9"><?= e($record['treatment'] ?? '') ?></dd>
                    <dt class="col-sm-3">Notes</dt><dd class="col-sm-9"><?= e($record['notes'] ?? '') ?></dd>
                </dl>
            <?php else: ?>
                <div class="alert alert-warning">No medical record selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
