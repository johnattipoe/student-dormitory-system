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

$roles = [
    ROLE_ADMIN => 'Admin',
    ROLE_HOUSE_MASTER => 'House Master',
    ROLE_HOUSE_MISTRESS => 'House Mistress',
    ROLE_HOUSEPARENT => 'Houseparent',
    ROLE_SECURITY => 'Security',
    ROLE_NURSE => 'Nurse',
    ROLE_STUDENT => 'Student',
];
// Custom roles managed via Firestore (roles collection)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleKey = trim((string) ($_POST['role_key'] ?? ''));
    $permissionMatrix = [
        'users' => trim((string) ($_POST['users'] ?? 'none')),
        'students' => trim((string) ($_POST['students'] ?? 'none')),
        'houses' => trim((string) ($_POST['houses'] ?? 'none')),
        'rooms' => trim((string) ($_POST['rooms'] ?? 'none')),
        'attendance' => trim((string) ($_POST['attendance'] ?? 'none')),
        'visitors' => trim((string) ($_POST['visitors'] ?? 'none')),
        'incidents' => trim((string) ($_POST['incidents'] ?? 'none')),
        'reports' => trim((string) ($_POST['reports'] ?? 'none')),
    ];

    if ($roleKey === '') {
        flash('error', 'Please choose a role.');
        redirect(url('views/admin/permissions/create.php'));
    }

    // Permissions managed via Firestore (permissions collection)
    // TODO: Implement Firebase write for custom permissions
    flash('info', 'Custom permissions management via Firestore coming soon.');
    redirect(url('views/admin/permissions/index.php'));
}

$pageTitle = 'Create Permission';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-key', 'label' => 'Permissions', 'href' => url('views/admin/permissions/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Create Permission', 'href' => url('views/admin/permissions/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 820px;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Create Permission</h5>
                <a href="<?= url('views/admin/permissions/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Permissions</a>
            </div>

            <form method="POST" action="<?= url('views/admin/permissions/create.php') ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select name="role_key" class="form-select" required>
                            <option value="">Select role</option>
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Access Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (['users','students','houses','rooms','attendance','visitors','incidents','reports'] as $module): ?>
                                <tr>
                                    <td><?= ucfirst(str_replace('_', ' ', $module)) ?></td>
                                    <td>
                                        <select name="<?= e($module) ?>" class="form-select">
                                            <option value="none">None</option>
                                            <option value="view">View</option>
                                            <option value="manage">Manage</option>
                                            <option value="full">Full</option>
                                            <option value="own">Own</option>
                                            <option value="limited">Limited</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Permission</button>
                    <a href="<?= url('views/admin/permissions/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
