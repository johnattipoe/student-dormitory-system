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

$pageTitle = 'Permissions';
$permissionModules = ['users', 'students', 'houses', 'rooms', 'room_allocation', 'attendance', 'visitors', 'visitor_requests', 'incidents', 'medical_records', 'reports', 'notifications', 'activity_logs', 'settings', 'announcements', 'message_parents', 'emergency_alerts', 'emergency_contacts', 'health_reports', 'audit_trail', 'backup_restore', 'profile'];
$permissions = require APP_ROOT . '/app/config/permissions/permissions.php';
$roles = [
    ROLE_ADMIN => 'Admin',
    ROLE_HOUSE_MASTER => 'House Master',
    ROLE_HOUSE_MISTRESS => 'House Mistress',
    ROLE_SENIOR_HOUSEPARENT => 'Senior Houseparent',
    ROLE_SECURITY => 'Security',
    ROLE_NURSE => 'Nurse',
    ROLE_STUDENT => 'Student',
];
$savedPermissionKeys = [];

try {
    $savedPermissions = FirebaseService::getInstance()->getCollection(COL_PERMISSIONS, [], 200);
    foreach ($savedPermissions as $savedPermission) {
        $roleKey = (string) ($savedPermission['role'] ?? '');
        if ($roleKey !== '' && !empty($savedPermission['levels']) && is_array($savedPermission['levels'])) {
            $permissions[$roleKey] = array_replace($permissions[$roleKey] ?? [], $savedPermission['levels']);
            $roles[$roleKey] = ucwords(str_replace('_', ' ', $roleKey));
            $savedPermissionKeys[$roleKey] = true;
        }
    }
} catch (Throwable $e) {
    // The built-in permission matrix remains available when Firestore is unavailable.
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-key', 'label' => 'Permissions', 'href' => url('views/admin/permissions/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Permissions</h5>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Users</a>
                <a href="<?= url('views/admin/permissions/create/create.php') ?>" class="btn btn-success btn-sm">Add Permission</a>
                <a href="<?= url('views/admin/roles/index/index.php') ?>" class="btn btn-primary btn-sm">Roles</a>
            </div>
        </div>

        <div class="row g-3 mb-3"><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Roles covered</small><strong class="fs-2"><?= e((string) count($roles)) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Custom matrices</small><strong class="fs-2 text-primary"><?= e((string) count($savedPermissionKeys)) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Modules</small><strong class="fs-2"><?= e((string) count($permissionModules)) ?></strong></div></div></div>

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
                            <?php foreach ($permissionModules as $module): ?>
                                <th><?= e(ucwords(str_replace('_', ' ', $module))) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <tr>
                                <td><strong><?= e($roleLabel) ?></strong></td>
                                <?php
                                $level = $permissions[$roleKey] ?? [];
                                ?>
                                <?php foreach ($permissionModules as $module): ?>
                                    <td><?= e($level[$module] ?? 'none') ?></td>
                                <?php endforeach; ?>
                                <td><?php if (!empty($savedPermissionKeys[$roleKey])): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/permissions/edit/edit.php?id=' . urlencode($roleKey)) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/permissions/delete/delete.php?id=' . urlencode($roleKey)) ?>">Delete</a><?php else: ?><span class="text-muted small">Config</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
