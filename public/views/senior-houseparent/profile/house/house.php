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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\UserService;

$user = current_user() ?? [];
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

// Load house staff
$allUsers = (new UserService())->all();
$houseStaff = array_values(array_filter($allUsers, function ($u) use ($houseId) {
    $uHouse = (string) ($u['houseId'] ?? $u['house_id'] ?? '');
    $role = (string) ($u['role'] ?? '');
    return $uHouse === $houseId && in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true);
}));

$studentCount = $houseId !== '' ? StudentService::count($houseId) : 0;
$roomStats = $houseId !== '' ? RoomService::occupancyStats($houseId) : ['rooms' => 0, 'capacity' => 0, 'occupied' => 0, 'vacant' => 0, 'occupancyRate' => 0];

$pageTitle = 'House Overview & Team';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/senior-houseparent/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><?= e($houseName) ?> — Dormitory Details & Staff Team</h5>
                <p class="text-muted mb-0">Overview of house infrastructure, room statistics, and assigned house staff.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total Students</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $studentCount) ?></strong>
                    <span class="small text-muted">Enrolled residents</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total Rooms</small>
                    <strong class="fs-2 text-info my-1"><?= e((string) ($roomStats['rooms'] ?? 0)) ?></strong>
                    <span class="small text-muted"><?= e((string) ($roomStats['capacity'] ?? 0)) ?> Total Bed Spaces</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Occupied Beds</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) ($roomStats['occupied'] ?? 0)) ?></strong>
                    <span class="small text-muted"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>% Occupancy</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Vacant Beds</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) ($roomStats['vacant'] ?? 0)) ?></strong>
                    <span class="small text-muted">Available spaces</span>
                </div>
            </div>
        </div>

        <br>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-building me-2 text-primary"></i> House Infrastructure & Location</h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">House Name</dt>
                        <dd class="col-sm-8 fw-bold text-primary"><?= e($houseName) ?></dd>

                        <dt class="col-sm-4 text-muted">Gender Type</dt>
                        <dd class="col-sm-8"><?= e(ucfirst((string)($house['gender'] ?? $house['type'] ?? 'General'))) ?></dd>

                        <dt class="col-sm-4 text-muted">Building Block</dt>
                        <dd class="col-sm-8"><?= e($house['block'] ?? $house['location'] ?? 'Main Dormitory Complex') ?></dd>

                        <dt class="col-sm-4 text-muted">Floors / Levels</dt>
                        <dd class="col-sm-8"><?= e((string)($house['floors'] ?? 'Ground & 1st Floor')) ?></dd>

                        <dt class="col-sm-4 text-muted">Description</dt>
                        <dd class="col-sm-8"><?= e($house['description'] ?? 'Official residential hall for boarding students.') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-people me-2 text-primary"></i> House Management & Staff Team</h6>
                    <?php if (empty($houseStaff)): ?>
                        <p class="text-muted mb-0">No other staff members assigned to this house.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($houseStaff as $staff): ?>
                                <?php
                                    $stName = trim(($staff['name'] ?? '') ?: (($staff['fullName'] ?? '') ?: (($staff['firstName'] ?? '') . ' ' . ($staff['lastName'] ?? ''))));
                                    if ($stName === '') $stName = $staff['displayName'] ?? $staff['username'] ?? 'Staff Member';
                                    $stRole = ucwords(str_replace(['_', '-'], ' ', (string)($staff['role'] ?? 'Staff')));
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <div>
                                        <strong><?= e($stName) ?></strong>
                                        <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= e($staff['email'] ?? '—') ?> | <i class="bi bi-telephone me-1"></i><?= e($staff['phone'] ?? '—') ?></div>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= e($stRole) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Module Links -->
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/students/index/index.php') ?>">
                <i class="bi bi-mortarboard me-1"></i> View Students
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/rooms/index/index.php') ?>">
                <i class="bi bi-door-open me-1"></i> Manage Rooms
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/beds/index/index.php') ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i> Bed Allocations
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/attendance/index/index.php') ?>">
                <i class="bi bi-calendar-check me-1"></i> Roll Call Attendance
            </a>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

