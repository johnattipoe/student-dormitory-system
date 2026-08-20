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
use App\Services\RoomService;

$pageTitle = 'Room Allocation';
$rooms = RoomService::all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-diagram-3', 'label' => 'Allocation', 'href' => url('views/admin/rooms/allocation.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-0">Room Allocation</h5>
            <p class="text-muted">Review current room availability and occupancy.</p>
        </div>
        <div class="row g-3">
            <?php foreach ($rooms as $room): ?>
                <div class="col-md-4">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">Room <?= e($room['roomNumber'] ?? '-') ?></h6>
                                <p class="text-muted mb-0">House: <?= e($room['houseId'] ?? '-') ?></p>
                            </div>
                            <span class="badge bg-<?= (($room['occupied'] ?? 0) >= ($room['capacity'] ?? 1)) ? 'danger' : 'success' ?> bg-opacity-10 text-<?= (($room['occupied'] ?? 0) >= ($room['capacity'] ?? 1)) ? 'danger' : 'success' ?> rounded-pill">
                                <?= e($room['occupied'] ?? 0) ?>/<?= e($room['capacity'] ?? 0) ?>
                            </span>
                        </div>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Status: <?= e($room['status'] ?? 'unknown') ?></span>
                            <div class="mt-3"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/rooms/view.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">View room</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/rooms/edit.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">Edit</a></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>