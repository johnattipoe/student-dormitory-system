<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseAdminAuthService;
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'Users';
$userService = new UserService();
$users = $userService->all();
$authUsers = [];

if (FirebaseAdminAuthService::credentialsAvailable()) {
    $authUsers = FirebaseAdminAuthService::listAuthUsers(500);
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Users</h5>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="<?= url('views/admin/users/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add User</a>
                <a href="<?= url('views/admin/roles/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-check"></i> Roles</a>
                <a href="<?= url('views/admin/permissions/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key"></i> Permissions</a>

                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-list"></i> More
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= url('views/admin/users/create.php') ?>"><i class="bi bi-person-plus"></i> New User</a></li>
                        <li><a class="dropdown-item" href="<?= url('views/admin/settings/index.php') ?>"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="<?= url('views/admin/activity-logs/index.php') ?>"><i class="bi bi-clock-history"></i> Activity Logs</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('views/admin/users/index.php') ?>"><i class="bi bi-arrow-repeat"></i> Refresh</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <?php if (!empty($authUsers)): ?>
                <div class="mb-3">
                    <h6 class="mb-2">Firebase Auth (from Admin)</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped">
                            <thead>
                            <tr><th>Email</th><th>UID</th><th>Provider</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($authUsers as $au): ?>
                                <tr>
                                    <td><?= e($au['email'] ?? $au['displayName'] ?? '') ?></td>
                                    <td><code><?= e($au['localId'] ?? $au['uid'] ?? '') ?></code></td>
                                    <td><?= e(implode(',', array_column($au['providerUserInfo'] ?? [], 'providerId')) ?: 'password') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>House</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userRole = $user['role'] ?? '';
                    $userHouseId = $user['houseId'] ?? null;
                    $userHouseName = $userHouseId ? (HouseService::find($userHouseId)['name'] ?? 'Assigned') : '-';
                    ?>
                    <tr>
                        <td><?= e($user['name'] ?? '') ?></td>
                        <td><?= e($user['email'] ?? '') ?></td>
                        <td><?= e(str_replace('_', ' ', ucfirst($userRole))) ?></td>
                        <td><?= e(in_array($userRole, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) ? $userHouseName : '-') ?></td>
                        <td><span class="badge bg-<?= ($user['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($user['status'] ?? '') ?></span></td>
                        <td>
                            <a href="<?= url('views/admin/users/view.php?id=' . urlencode($user['id'] ?? $user['uid'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="<?= url('views/admin/users/edit.php?id=' . urlencode($user['id'] ?? $user['uid'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
