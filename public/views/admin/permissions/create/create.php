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

$permissionModules = ['users', 'students', 'houses', 'rooms', 'room_allocation', 'attendance', 'visitors', 'visitor_requests', 'incidents', 'medical_records', 'reports', 'notifications', 'activity_logs', 'settings', 'announcements', 'message_parents', 'emergency_alerts', 'emergency_contacts', 'health_reports', 'audit_trail', 'backup_restore', 'profile'];
$roles = [
    ROLE_ADMIN => 'Admin',
    ROLE_HOUSE_MASTER => 'House Master',
    ROLE_HOUSE_MISTRESS => 'House Mistress',
    ROLE_SENIOR_HOUSEPARENT => 'Senior Houseparent',
    ROLE_SECURITY => 'Security',
    ROLE_NURSE => 'Nurse',
    ROLE_STUDENT => 'Student',
];
try {
    $customRoles = FirebaseService::getInstance()->getCollection(COL_ROLES, [], 200);
    foreach ($customRoles as $customRole) {
        $roleKey = trim((string) ($customRole['key'] ?? ''));
        if ($roleKey !== '') {
            $roles[$roleKey] = (string) ($customRole['name'] ?? $roleKey);
        }
    }
} catch (Throwable $e) {
    // Built-in roles remain available when Firestore is unavailable.
}

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
        'notifications' => trim((string) ($_POST['notifications'] ?? 'none')),
        'activity_logs' => trim((string) ($_POST['activity_logs'] ?? 'none')),
        'settings' => trim((string) ($_POST['settings'] ?? 'none')),
        'announcements' => trim((string) ($_POST['announcements'] ?? 'none')),
        'message_parents' => trim((string) ($_POST['message_parents'] ?? 'none')),
        'emergency_alerts' => trim((string) ($_POST['emergency_alerts'] ?? 'none')),
        'emergency_contacts' => trim((string) ($_POST['emergency_contacts'] ?? 'none')),
        'health_reports' => trim((string) ($_POST['health_reports'] ?? 'none')),
        'audit_trail' => trim((string) ($_POST['audit_trail'] ?? 'none')),
        'backup_restore' => trim((string) ($_POST['backup_restore'] ?? 'none')),
        'profile' => trim((string) ($_POST['profile'] ?? 'none')),
        'room_allocation' => trim((string) ($_POST['room_allocation'] ?? 'none')),
        'visitor_requests' => trim((string) ($_POST['visitor_requests'] ?? 'none')),
        'medical_records' => trim((string) ($_POST['medical_records'] ?? 'none')),
    ];

    if ($roleKey === '') {
        flash('error', 'Please choose a role.');
        redirect(url('views/admin/permissions/create/create.php'));
    }

    try {
        FirebaseService::getInstance()->addDocument(COL_PERMISSIONS, [
            'role' => $roleKey,
            'levels' => $permissionMatrix,
            'updatedBy' => current_user()['uid'] ?? current_user()['id'] ?? 'admin',
        ], $roleKey);
        flash('success', 'Permission matrix saved successfully.');
    } catch (Throwable $e) {
        flash('error', 'Unable to save permissions: ' . $e->getMessage());
    }
    redirect(url('views/admin/permissions/index/index.php'));
}

$pageTitle = 'Create Permission';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-key', 'label' => 'Permissions', 'href' => url('views/admin/permissions/index/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Create Permission', 'href' => url('views/admin/permissions/create/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 820px;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Create Permission</h5>
                <a href="<?= url('views/admin/permissions/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Permissions</a>
            </div>

            <form method="POST" action="<?= url('views/admin/permissions/create/create.php') ?>">
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
                            <?php foreach ($permissionModules as $module): ?>
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
                    <a href="<?= url('views/admin/permissions/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
