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

$pageTitle = 'Permissions';
$permissions = require APP_ROOT . '/app/config/permissions.php';
// Custom permissions and custom roles managed via Firestore
$roles = [
    ROLE_ADMIN => 'Admin',
    ROLE_HOUSE_MASTER => 'House Master',
    ROLE_HOUSE_MISTRESS => 'House Mistress',
    ROLE_HOUSEPARENT => 'Houseparent',
    ROLE_SECURITY => 'Security',
    ROLE_NURSE => 'Nurse',
    ROLE_STUDENT => 'Student',
];

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-key', 'label' => 'Permissions', 'href' => url('views/admin/permissions/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Permissions</h5>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Users</a>
                <a href="<?= url('views/admin/permissions/create.php') ?>" class="btn btn-success btn-sm">Add Permission</a>
                <a href="<?= url('views/admin/roles/index.php') ?>" class="btn btn-primary btn-sm">Roles</a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-1">Access Matrix</h6>
                    <p class="text-muted mb-0">Current permission levels assigned to each role.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">Current matrix</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle table-bordered data-table" data-no-data-table="true">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Users</th>
                            <th>Students</th>
                            <th>Houses</th>
                            <th>Rooms</th>
                            <th>Attendance</th>
                            <th>Visitors</th>
                            <th>Incidents</th>
                            <th>Reports</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <tr>
                                <td><strong><?= e($roleLabel) ?></strong></td>
                                <?php
                                $level = $permissions[$roleKey] ?? [];
                                ?>
                                <td><?= e($level['users'] ?? 'none') ?></td>
                                <td><?= e($level['students'] ?? 'none') ?></td>
                                <td><?= e($level['houses'] ?? 'none') ?></td>
                                <td><?= e($level['rooms'] ?? 'none') ?></td>
                                <td><?= e($level['attendance'] ?? 'none') ?></td>
                                <td><?= e($level['visitors'] ?? 'none') ?></td>
                                <td><?= e($level['incidents'] ?? 'none') ?></td>
                                <td><?= e($level['reports'] ?? 'none') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
