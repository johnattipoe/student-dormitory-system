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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = $_GET['id'] ?? '';
$record = $id ? FirebaseService::getInstance()->getDocument(COL_MEDICAL_RECORDS, $id) : null;
$pageTitle = 'Edit Medical Record';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => url('views/medical/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px;">
            <h5 class="mb-3">Edit Medical Record</h5>
            <?php if ($record): ?>
                <form method="POST" action="<?= url('views/medical/edit.php') ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" value="<?= e($record['diagnosis'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Treatment</label>
                            <textarea name="treatment" class="form-control" rows="3" required><?= e($record['treatment'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= e($record['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">Update Record</button>
                        <a href="<?= url('views/medical/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">No medical record found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
