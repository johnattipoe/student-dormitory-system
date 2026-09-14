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
                    <p class="text-muted mb-0">Click a role to view its detailed access permissions.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">Current matrix</span>
            </div>

            <style>
                .role-permission-accordion {
                    display: grid;
                    gap: 0.8rem;
                }
                .role-permission-item {
                    border: 1px solid rgba(15, 76, 150, 0.12);
                    border-radius: 12px;
                    overflow: hidden;
                    background: #ffffff;
                    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
                }
                .role-permission-header {
                    width: 100%;
                    border: none;
                    background: linear-gradient(90deg, #eef5ff 0%, #f9fbff 100%);
                    border-left: 5px solid #1d7ef2;
                    padding: 0.9rem 1.1rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    text-align: left;
                    color: #0f172a;
                    font-weight: 700;
                    letter-spacing: 0.01em;
                    border-radius: 0;
                }
                .role-permission-header small {
                    font-weight: 600;
                    color: #475569;
                }
                .role-permission-header .accordion-button::after {
                    margin-left: 1rem;
                }
                .role-permission-header:focus,
                .role-permission-header:active {
                    box-shadow: none;
                }
                .role-permission-body {
                    padding: 1rem 1rem 1.1rem;
                    background: #ffffff;
                }
                .role-detail-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                    gap: 0.7rem;
                }
                .role-detail-card {
                    border: 1px solid #edf2f7;
                    border-radius: 10px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    padding: 0.75rem 0.8rem;
                }
                .role-detail-card .module-name {
                    display: block;
                    font-size: 0.7rem;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 0.38rem;
                }
                .permission-badge {
                    display: inline-block;
                    min-width: 74px;
                    text-align: center;
                    border-radius: 999px;
                    padding: 0.3rem 0.65rem;
                    font-size: 0.72rem;
                    font-weight: 700;
                    text-transform: capitalize;
                    background: #edf5ff;
                    color: #1b5fcc;
                }
                .permission-badge.full { background: rgba(25,135,84,0.12); color: #198754; }
                .permission-badge.manage { background: rgba(255,193,7,0.12); color: #b7791f; }
                .permission-badge.view { background: rgba(13,110,253,0.08); color: #0d6efd; }
                .permission-badge.none { background: rgba(108,117,125,0.10); color: #6c757d; }
            </style>

            <div class="role-permission-accordion accordion" id="rolePermissionAccordion">
                <?php foreach ($roles as $roleKey => $roleLabel): ?>
                    <?php $level = $permissions[$roleKey] ?? []; ?>
                    <?php $moduleCount = count(array_filter($permissionModules, static fn($module) => (($level[$module] ?? 'none') !== 'none'))); ?>
                    <div class="role-permission-item accordion-item">
                        <h2 class="accordion-header" id="heading-<?= e($roleKey) ?>">
                            <button class="role-permission-header accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= e($roleKey) ?>" aria-expanded="false" aria-controls="collapse-<?= e($roleKey) ?>">
                                <span><?= e($roleLabel) ?></span>
                                <small><?= e((string) $moduleCount) ?> modules active</small>
                            </button>
                        </h2>
                        <div id="collapse-<?= e($roleKey) ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= e($roleKey) ?>" data-bs-parent="#rolePermissionAccordion">
                            <div class="role-permission-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <strong><?= e($roleLabel) ?> access</strong>
                                    <?php if (!empty($savedPermissionKeys[$roleKey])): ?>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/permissions/edit/edit.php?id=' . urlencode($roleKey)) ?>">Edit</a>
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/permissions/delete/delete.php?id=' . urlencode($roleKey)) ?>">Delete</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Built-in config</span>
                                    <?php endif; ?>
                                </div>

                                <div class="role-detail-grid">
                                    <?php foreach ($permissionModules as $module): ?>
                                        <?php $moduleValue = strtolower((string) ($level[$module] ?? 'none')); ?>
                                        <div class="role-detail-card">
                                            <span class="module-name"><?= e(ucwords(str_replace('_', ' ', $module))) ?></span>
                                            <span class="permission-badge <?= e($moduleValue) ?>"><?= e($moduleValue === '' ? 'none' : $moduleValue) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
