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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\UserService;
use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\AttendanceService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$profile = $userId ? $userService->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Not Assigned');

$students = StudentService::all($houseId);
$totalStudents = count($students);

$rooms = RoomService::all($houseId);
$totalRooms = count($rooms);

$today = date('Y-m-d');
$attendance = AttendanceService::forDate($today, $houseId);
$presentCount = count(array_filter($attendance, fn($a) => ($a['status'] ?? 'present') === 'present'));

$roleTitle = current_role() === ROLE_HOUSE_MISTRESS ? 'House Mistress' : 'House Master';
$fullName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: $roleTitle;
$initials = strtoupper(substr($fullName, 0, 1) . (str_contains($fullName, ' ') ? substr(explode(' ', $fullName)[1], 0, 1) : 'M'));

$pageTitle = 'My Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/house-master/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width:72px;height:72px;">
                    <?= e($initials) ?>
                </div>
                <div>
                    <h4 class="mb-1 fw-bold text-dark"><?= e($fullName) ?></h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield-check me-1 text-primary"></i><?= e($roleTitle) ?> &bull; <i class="bi bi-house me-1"></i><?= e($houseName) ?>
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/profile/edit/edit.php') ?>">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/profile/security/security.php') ?>">
                    <i class="bi bi-shield-lock me-1"></i>Security
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/profile/house/house.php') ?>">
                    <i class="bi bi-building me-1"></i>House Info
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Assigned Students</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalStudents) ?></h3>
                            <span class="small text-muted"><?= e($houseName) ?></span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-mortarboard fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Dormitory Rooms</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $totalRooms) ?></h3>
                            <span class="small text-muted">Active room blocks</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-closed fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present Today</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $presentCount) ?></h3>
                            <span class="small text-muted">Roll call verified</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Assigned House</span>
                            <h3 class="fw-bold my-1 text-warning text-truncate" style="max-width:140px"><?= e($houseName) ?></h3>
                            <span class="small text-muted"><?= $houseId !== '' ? 'Active Assignment' : 'Unassigned' ?></span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-building fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Details & Quick Links -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal & Contact Information</h6>
                    </div>
                    <div class="card-body p-4">
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
                                <span class="text-muted small d-block">Phone Number</span>
                                <strong><?= e($user['phone'] ?? 'Not provided') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Office Room</span>
                                <strong><?= e($user['officeRoom'] ?? 'House Master Desk') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Office Hours</span>
                                <strong><?= e($user['officeHours'] ?? '8:00 AM - 6:00 PM') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Account Role</span>
                                <span class="badge bg-primary-subtle text-primary border"><?= e($roleTitle) ?></span>
                            </div>
                            <div class="col-12">
                                <span class="text-muted small d-block">Bio / Supervisory Note</span>
                                <p class="text-muted small mb-0"><?= e($user['bio'] ?? 'Dedicated to student welfare, dormitory safety, and scholastic discipline.') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2 text-primary"></i>Profile Navigation</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="<?= url('views/house-master/profile/edit/edit.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>
                                    <strong>Edit Contact Information</strong>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            <a href="<?= url('views/house-master/profile/security/security.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <i class="bi bi-shield-lock me-2 text-success"></i>
                                    <strong>Password & Account Security</strong>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            <a href="<?= url('views/house-master/profile/house/house.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <i class="bi bi-building me-2 text-warning"></i>
                                    <strong>Assigned House Specifications</strong>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
