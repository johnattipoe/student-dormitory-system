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

use App\Services\FirebaseService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleKey = strtolower(trim((string) ($_POST['role_key'] ?? '')));
    $roleName = trim((string) ($_POST['name'] ?? ''));
    $dashboard = trim((string) ($_POST['dashboard'] ?? 'Custom Dashboard'));
    $houseAccess = trim((string) ($_POST['house_access'] ?? 'Custom'));
    $description = trim((string) ($_POST['description'] ?? 'Custom role created by admin.'));

    if ($roleKey === '' || $roleName === '') {
        flash('error', 'Role key and name are required.');
        redirect(url('views/admin/roles/create/create.php'));
    }

    if (!preg_match('/^[a-z][a-z0-9_]*$/', $roleKey)) {
        flash('error', 'Role key must start with a letter and contain only lowercase letters, numbers, and underscores.');
        redirect(url('views/admin/roles/create/create.php'));
    }

    try {
        FirebaseService::getInstance()->addDocument(COL_ROLES, [
            'key' => $roleKey,
            'name' => $roleName,
            'dashboard' => $dashboard,
            'house_access' => $houseAccess,
            'description' => $description,
            'createdBy' => current_user()['uid'] ?? current_user()['id'] ?? 'admin',
        ], $roleKey);
        flash('success', 'Custom role created successfully.');
    } catch (Throwable $e) {
        flash('error', 'Unable to create role: ' . $e->getMessage());
    }
    redirect(url('views/admin/roles/index/index.php'));
}

$pageTitle = 'Create Role';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-shield-check', 'label' => 'Roles', 'href' => url('views/admin/roles/index/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Create Role', 'href' => url('views/admin/roles/create/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 720px;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Create Role</h5>
                <a href="<?= url('views/admin/roles/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Roles</a>
            </div>

            <form method="POST" action="<?= url('views/admin/roles/create/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Role Key</label>
                        <input name="role_key" class="form-control" placeholder="e.g. senior_houseparent" required>
                        <div class="form-text">Use lowercase letters, underscores, and no spaces.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Role Name</label>
                        <input name="name" class="form-control" placeholder="Senior Houseparent" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Dashboard</label>
                        <input name="dashboard" class="form-control" value="Custom Dashboard">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">House Access</label>
                        <input name="house_access" class="form-control" value="Custom">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">Custom role created by admin.</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Role</button>
                    <a href="<?= url('views/admin/roles/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
