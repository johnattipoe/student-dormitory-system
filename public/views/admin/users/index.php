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

use App\Services\FirebaseAdminAuthService;
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'Users';
$userService = new UserService();
$users = $userService->all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $users = array_values(array_filter($users, function ($user) use ($search) {
        return str_contains(strtolower(trim(($user['name'] ?? '') . ' ' . ($user['email'] ?? '') . ' ' . ($user['role'] ?? ''))), $search);
    }));
}
$authUsers = [];

if (FirebaseAdminAuthService::credentialsAvailable()) {
    $authUsers = FirebaseAdminAuthService::listAuthUsers(500);
}
$totalUsers = count($users);
$activeUsers = count(array_filter($users, static fn(array $user): bool => ($user['status'] ?? 'active') === 'active'));
$staffUsers = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') !== 'student'));
$roleCount = count(array_unique(array_filter(array_map(static fn(array $user): string => (string) ($user['role'] ?? ''), $users))));
$roleCounts = [];
foreach ($users as $userRecord) {
    $roleKey = strtolower(trim((string) ($userRecord['role'] ?? '')));
    $roleCounts[$roleKey] = ($roleCounts[$roleKey] ?? 0) + 1;
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
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-9">
                    <input name="search" class="form-control form-control-sm" placeholder="Search name, email, or role" value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm">Filter</button> 
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/users/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <br>

        <div class="row g-3 mb-4 admin-user-metrics">
            <div class="col-md-3"><div class="card stat-card p-3 d-flex flex-row align-items-center gap-3"><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div><div><div class="text-muted small">Total users</div><div class="fs-4 fw-bold"><?= e((string) $totalUsers) ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 d-flex flex-row align-items-center gap-3"><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-check"></i></div><div><div class="text-muted small">Active users</div><div class="fs-4 fw-bold"><?= e((string) $activeUsers) ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 d-flex flex-row align-items-center gap-3"><div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-badge"></i></div><div><div class="text-muted small">Staff accounts</div><div class="fs-4 fw-bold"><?= e((string) $staffUsers) ?></div></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 d-flex flex-row align-items-center gap-3"><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-shield-check"></i></div><div><div class="text-muted small">Role groups</div><div class="fs-4 fw-bold"><?= e((string) $roleCount) ?></div></div></div></div>
        </div>

        <section class="admin-role-block mb-4">
            <div class="admin-section-heading"><div><span class="admin-kicker">Access overview</span><h2>Users by role</h2><p>Quick distribution of accounts currently in view.</p></div><i class="bi bi-diagram-3"></i></div>
            <div class="row g-3">
                <?php $roleCards = [
                    ['key' => 'student', 'label' => 'Students', 'icon' => 'bi-mortarboard', 'tone' => 'blue'],
                    ['key' => 'admin', 'label' => 'Admins', 'icon' => 'bi-shield-lock', 'tone' => 'red'],
                    ['key' => 'houseparent', 'label' => 'Houseparents', 'icon' => 'bi-house-heart', 'tone' => 'green'],
                    ['key' => 'house_master', 'label' => 'House Masters', 'icon' => 'bi-building-check', 'tone' => 'purple'],
                    ['key' => 'security', 'label' => 'Security', 'icon' => 'bi-shield-check', 'tone' => 'orange'],
                    ['key' => 'nurse', 'label' => 'Nurses', 'icon' => 'bi-heart-pulse', 'tone' => 'pink'],
                ]; ?>
                <?php foreach ($roleCards as $roleCard): ?>
                    <div class="col-6 col-lg-4 col-xl-2"><div class="admin-role-card"><span class="admin-role-icon <?= e($roleCard['tone']) ?>"><i class="bi <?= e($roleCard['icon']) ?>"></i></span><div><small><?= e($roleCard['label']) ?></small><strong><?= e((string) ($roleCounts[$roleCard['key']] ?? 0)) ?></strong></div></div></div>
                <?php endforeach; ?>
            </div>
        </section>
        
    
        
        <div class="card stat-card p-3">
            <?php if (!empty($authUsers)): ?>
                <div class="mb-3">
                    <h6 class="mb-2">Firebase Auth (from Admin)</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped">
                            <thead>
                            <tr>
                                <th>Email</th>
                                <th>UID</th>
                                <th>Provider</th>
                            </tr>
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
                            <a href="<?= url('views/admin/users/delete.php?id=' . urlencode($user['id'] ?? $user['uid'] ?? '')) ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
