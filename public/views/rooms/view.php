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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\RoomService;

$id = $_GET['id'] ?? '';
$room = $id ? RoomService::find($id) : null;
$houseMap = [];
foreach (HouseService::all() as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
}
$pageTitle = 'Room Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/rooms/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Room Details</h5>
            <?php if ($room): ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Room Number</dt><dd class="col-sm-9"><?= e($room['roomNumber'] ?? '') ?></dd>
                    <dt class="col-sm-3">House</dt><dd class="col-sm-9"><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) ?></dd>
                    <dt class="col-sm-3">Capacity</dt><dd class="col-sm-9"><?= e((string) ($room['capacity'] ?? 0)) ?></dd>
                    <dt class="col-sm-3">Occupied</dt><dd class="col-sm-9"><?= e((string) ($room['occupied'] ?? 0)) ?></dd>
                    <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= e($room['status'] ?? '') ?></dd>
                </dl>
            <?php else: ?>
                <div class="alert alert-warning">No room selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
