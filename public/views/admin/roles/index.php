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

$pageTitle = 'Roles';
// Custom roles managed via Firestore (roles collection)

$displayRoles = [
    ['key' => ROLE_ADMIN, 'name' => 'Admin', 'dashboard' => 'Admin Dashboard', 'house_access' => 'All houses', 'description' => 'Full control over the dormitory system.'],
    ['key' => ROLE_HOUSE_MASTER, 'name' => 'House Master', 'dashboard' => 'House Master Dashboard', 'house_access' => 'Assigned house', 'description' => 'Manages attendance, rooms, students, incidents and reports for their house.'],
    ['key' => ROLE_HOUSE_MISTRESS, 'name' => 'House Mistress', 'dashboard' => 'House Master Dashboard', 'house_access' => 'Assigned house', 'description' => 'Same house management duties as the House Master.'],
    ['key' => ROLE_HOUSEPARENT, 'name' => 'Houseparent', 'dashboard' => 'Houseparent Dashboard', 'house_access' => 'Supervision only', 'description' => 'Monitors house operations and related records without being assigned to a house.'],
    ['key' => ROLE_NURSE, 'name' => 'Nurse', 'dashboard' => 'Nurse Dashboard', 'house_access' => 'None', 'description' => 'Handles medical records and student wellness tracking.'],
    ['key' => ROLE_SECURITY, 'name' => 'Security', 'dashboard' => 'Security Dashboard', 'house_access' => 'None', 'description' => 'Manages visitors and security-related incidents.'],
    ['key' => ROLE_STUDENT, 'name' => 'Student', 'dashboard' => 'Student Dashboard', 'house_access' => 'Own profile', 'description' => 'Accesses personal attendance, room, and notification data.'],
];

// Firestore roles will be merged here when Firestore integration is complete

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-shield-check', 'label' => 'Roles', 'href' => url('views/admin/roles/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Roles</h5>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Users</a>
                <a href="<?= url('views/admin/roles/create.php') ?>" class="btn btn-success btn-sm">Add Role</a>
                <a href="<?= url('views/admin/permissions/index.php') ?>" class="btn btn-primary btn-sm">Permissions</a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-1">System Roles</h6>
                    <p class="text-muted mb-0">Available user access levels across the dormitory system.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">7 roles</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle data-table" data-no-data-table="true">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Dashboard</th>
                            <th>House Access</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayRoles as $role): ?>
                            <tr>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-secondary';
                                    $roleName = strtolower((string) ($role['name'] ?? 'Custom'));
                                    if (strpos($roleName, 'admin') !== false) $badgeClass = 'bg-primary';
                                    elseif (strpos($roleName, 'master') !== false || strpos($roleName, 'mistress') !== false) $badgeClass = 'bg-info text-dark';
                                    elseif (strpos($roleName, 'parent') !== false) $badgeClass = 'bg-secondary';
                                    elseif (strpos($roleName, 'nurse') !== false) $badgeClass = 'bg-success';
                                    elseif (strpos($roleName, 'security') !== false) $badgeClass = 'bg-warning text-dark';
                                    elseif (strpos($roleName, 'student') !== false) $badgeClass = 'bg-dark';
                                    elseif (isset($role['key']) && $role['key'] !== (string) $role['key']) { $badgeClass = 'bg-success'; }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= e($role['name']) ?></span>
                                </td>
                                <td><?= e($role['dashboard']) ?></td>
                                <td><?= e($role['house_access']) ?></td>
                                <td><?= e($role['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
