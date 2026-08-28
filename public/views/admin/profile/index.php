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

use App\Services\UserService;
use App\Services\StudentService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$profile = $userId ? $userService->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$allUsers = $userService->all();
$totalStaff = count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') !== ROLE_STUDENT));
$totalStudents = count(StudentService::all());
$totalHouses = count(HouseService::all());

$fullName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Administrator';
$initials = strtoupper(substr($fullName, 0, 1) . (str_contains($fullName, ' ') ? substr(explode(' ', $fullName)[1], 0, 1) : 'A'));

$pageTitle = 'Administrator Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/admin/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <!-- Hero Header -->
        <div class="card stat-card p-4 mb-4 bg-primary text-white border-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width: 72px; height: 72px;">
                        <?= e($initials) ?>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold"><?= e($fullName) ?></h4>
                        <p class="mb-0 text-white-50">
                            <i class="bi bi-shield-lock me-1"></i> System Administrator &bull; Institutional Superuser
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light btn-sm" href="<?= url('views/admin/profile/edit/edit.php') ?>">
                        <i class="bi bi-pencil me-1"></i> Edit Profile
                    </a>
                    <a class="btn btn-outline-light btn-sm" href="<?= url('views/admin/profile/security/security.php') ?>">
                        <i class="bi bi-shield-lock me-1"></i> Security & Password
                    </a>
                    <a class="btn btn-outline-light btn-sm" href="<?= url('views/admin/settings/index/index.php') ?>">
                        <i class="bi bi-gear me-1"></i> System Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total Dormitory Students</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalStudents) ?></strong>
                    <span class="small text-muted">Active enrollments</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                    <small class="text-muted">Dormitory Staff & Officers</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) $totalStaff) ?></strong>
                    <span class="small text-muted">House masters, nurses & security</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-info">
                    <small class="text-muted">Dormitory Houses</small>
                    <strong class="fs-2 text-info my-1"><?= e((string) $totalHouses) ?></strong>
                    <span class="small text-muted">Institutional facilities</span>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i> Administrative Profile Details</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Full Name</span>
                            <strong><?= e($fullName) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Email Address</span>
                            <strong><?= e($user['email'] ?? '—') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Primary Contact</span>
                            <strong><?= e($user['phone'] ?? 'Not provided') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Administrative Role</span>
                            <span class="badge bg-primary">Administrator</span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small d-block">Role Scope</span>
                            <p class="text-muted small mb-0">Full read, write, export, and security authorization across all dormitory modules.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2 text-primary"></i> Quick Shortcuts</h6>
                    <div class="list-group list-group-flush">
                        <a href="<?= url('views/admin/profile/edit/edit.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><i class="bi bi-pencil-square me-2 text-primary"></i> <strong>Edit Contact Info</strong></div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="<?= url('views/admin/profile/security/security.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><i class="bi bi-shield-lock me-2 text-success"></i> <strong>Security & Password</strong></div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="<?= url('views/admin/users/index/index.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><i class="bi bi-people me-2 text-warning"></i> <strong>User & Staff Management</strong></div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

