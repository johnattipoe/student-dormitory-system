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
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-people-fill text-primary me-2"></i>User &amp; Staff Access Directory
                </h4>
                <p class="text-muted mb-0">Manage system authentication accounts, role permissions, and access levels</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="<?= url('views/admin/users/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </a>
                <a href="<?= url('views/admin/roles/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-check me-1"></i> Roles
                </a>
                <a href="<?= url('views/admin/permissions/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-key me-1"></i> Permissions
                </a>
            </div>
        </div>

        <!-- KPI Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Accounts</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalUsers) ?></h3>
                            <span class="small text-muted">All registered profiles</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Status</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeUsers) ?></h3>
                            <span class="small text-muted"><?= $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 100 ?>% active users</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Staff Accounts</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $staffUsers) ?></h3>
                            <span class="small text-muted">Administrative staff</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-person-badge fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Role Groups</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $roleCount) ?></h3>
                            <span class="small text-muted">Permission tiers</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-shield-check fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Badges Strip -->
        <div class="row g-2 mb-4">
            <?php $roleCards = [
                ['key' => 'student', 'label' => 'Students', 'icon' => 'bi-mortarboard', 'color' => 'primary'],
                ['key' => 'admin', 'label' => 'Admins', 'icon' => 'bi-shield-lock', 'color' => 'danger'],
                ['key' => 'senior-houseparent', 'label' => 'Senior Houseparents', 'icon' => 'bi-house-heart', 'color' => 'success'],
                ['key' => 'house_master', 'label' => 'House Masters', 'icon' => 'bi-building-check', 'color' => 'info'],
                ['key' => 'security', 'label' => 'Security', 'icon' => 'bi-shield-check', 'color' => 'dark'],
                ['key' => 'nurse', 'label' => 'Nurses', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
            ]; ?>
            <?php foreach ($roleCards as $roleCard): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card p-2 border-0 shadow-sm text-center">
                        <small class="text-muted d-block small"><?= e($roleCard['label']) ?></small>
                        <strong class="fs-5 text-<?= $roleCard['color'] ?>"><?= e((string) ($roleCounts[$roleCard['key']] ?? 0)) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" class="form-control form-control-sm border-start-0" placeholder="Search by name, email address, or role..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/users/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>System User Accounts</h6>
                <small class="text-muted">Showing <?= count($users) ?> accounts</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>User Name</th>
                            <th>Email Address</th>
                            <th>Role Permission</th>
                            <th>Assigned House</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No user accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $userRole = $user['role'] ?? '';
                                $userHouseId = $user['houseId'] ?? null;
                                $userHouseName = $userHouseId ? (HouseService::find($userHouseId)['name'] ?? 'Assigned') : '-';
                                $uId = (string) ($user['id'] ?? $user['uid'] ?? '');
                                ?>
                                <tr>
                                    <td><strong class="text-dark"><?= e($user['name'] ?? 'User') ?></strong></td>
                                    <td><span class="text-muted font-monospace small"><?= e($user['email'] ?? '—') ?></span></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e(str_replace(['_', '-'], ' ', ucfirst($userRole))) ?>
                                        </span>
                                    </td>
                                    <td><?= e(in_array($userRole, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) ? $userHouseName : '—') ?></td>
                                    <td><span class="badge bg-<?= ($user['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst(e($user['status'] ?? 'active')) ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= url('views/admin/users/view/view.php?id=' . urlencode($uId)) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url('views/admin/users/edit/edit.php?id=' . urlencode($uId)) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= url('views/admin/users/delete/delete.php?id=' . urlencode($uId)) ?>" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
