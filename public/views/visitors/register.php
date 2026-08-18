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
$allowedRoles = [ROLE_SECURITY, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Register Visitor';
$errors = $_SESSION['_errors'] ?? []; unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']);

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Register Visitor', 'href' => url('views/visitors/register.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px">
            <h5 class="mb-3">Register Visitor</h5>
            <form method="POST" action="<?= url('views/visitors/register.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Visitor Name</label>
                        <input name="visitorName" class="form-control" value="<?= e($old['visitorName'] ?? '') ?>" required>
                        <?php if (!empty($errors['visitorName'])): ?><div class="text-danger small"><?= e($errors['visitorName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Student ID</label>
                        <input name="studentId" class="form-control" value="<?= e($old['studentId'] ?? '') ?>" required>
                        <?php if (!empty($errors['studentId'])): ?><div class="text-danger small"><?= e($errors['studentId']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <input name="purpose" class="form-control" value="<?= e($old['purpose'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Type</label>
                        <input name="idType" class="form-control" value="<?= e($old['idType'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Number</label>
                        <input name="idNumber" class="form-control" value="<?= e($old['idNumber'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Register</button>
                    <a href="<?= url('views/visitors/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>