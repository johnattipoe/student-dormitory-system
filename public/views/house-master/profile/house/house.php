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

use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\UserService;

$user = current_user() ?? [];
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$rooms = RoomService::all($houseId);

$capacity = (int) ($house['capacity'] ?? ($totalRooms = count($rooms) * 24));
if ($capacity <= 0) $capacity = 100;
$occupancy = count($students);
$occupancyPercent = min(100, round(($occupancy / $capacity) * 100));

// Fellow house masters assigned to same house
$allUsers = (new UserService())->all();
$houseStaff = array_values(array_filter($allUsers, function ($u) use ($houseId) {
    $role = (string) ($u['role'] ?? '');
    $uHouseId = (string) ($u['houseId'] ?? $u['house_id'] ?? '');
    return in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true) && $uHouseId === $houseId;
}));

$pageTitle = 'Assigned House Specifications';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/house-master/profile/index.php'), 'active' => true],
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
                <h5 class="mb-1">Assigned House Specifications</h5>
                <p class="text-muted mb-0">Overview of dormitory capacity, assigned staff team, and room metrics for <?= e($houseName) ?>.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <div class="row g-4" style="max-width: 950px;">
            <div class="col-md-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3"><i class="bi bi-building me-2 text-primary"></i> <?= e($houseName) ?> Overview</h6>
                    <div class="mb-3">
                        <span class="text-muted small d-block">House Name</span>
                        <strong><?= e($houseName) ?></strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small d-block">Gender Quota</span>
                        <strong><?= ucfirst(e((string)($house['gender'] ?? 'Mixed / Co-ed'))) ?></strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small d-block">Total Room Blocks</span>
                        <strong><?= count($rooms) ?> Active Rooms</strong>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Bed Occupancy (<?= $occupancy ?> / <?= $capacity ?> beds)</span>
                            <strong><?= $occupancyPercent ?>%</strong>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-<?= $occupancyPercent >= 95 ? 'danger' : ($occupancyPercent >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $occupancyPercent ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i> House Supervisory Team</h6>
                    <?php if (empty($houseStaff)): ?>
                        <p class="text-muted small">No other staff currently assigned to this house.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($houseStaff as $staff): ?>
                                <?php
                                    $sName = trim(($staff['name'] ?? '') ?: (($staff['fullName'] ?? '') ?: (($staff['firstName'] ?? '') . ' ' . ($staff['lastName'] ?? '')))) ?: 'House Staff';
                                    $sRole = ucfirst(str_replace(['_', '-'], ' ', (string)($staff['role'] ?? 'Staff')));
                                ?>
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="d-block small"><?= e($sName) ?></strong>
                                        <span class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($staff['email'] ?? '—') ?></span>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= e($sRole) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

