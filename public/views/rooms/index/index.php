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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\RoomService;

$pageTitle = 'Rooms';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allRooms = RoomService::all();
$rooms = array_slice($allRooms, ($page - 1) * $limit, $limit);
$totalRooms = count($allRooms);
$totalPages = max(1, (int) ceil($totalRooms / $limit));
$houses = HouseService::all();
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
}
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/rooms/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Rooms</h5>
            <?php if (current_role() === ROLE_ADMIN): ?>
                <a href="<?= url('views/rooms/create/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Room</a>
            <?php endif; ?>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <small class="text-muted">Showing <?= count($rooms) ?> of <?= e((string) $totalRooms) ?> rooms</small>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Room</th>
                    <th>House</th>
                    <th>Capacity</th>
                    <th>Occupied</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?= e($room['roomNumber'] ?? '') ?></td>
                        <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) ?></td>
                        <td><?= e((string) ($room['capacity'] ?? 0)) ?></td>
                        <td><?= e((string) ($room['occupied'] ?? 0)) ?></td>
                        <td><span class="badge bg-<?= ($room['status'] ?? 'available') === 'available' ? 'success' : 'secondary' ?>"><?= e($room['status'] ?? 'available') ?></span></td>
                        <td class="text-nowrap">
                            <?php if (current_role() === ROLE_ADMIN): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#roomEditModal-<?= e((string) ($room['id'] ?? '')) ?>">Edit</button>
                            <?php else: ?>
                                <span class="text-muted small">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
    </div>
</div>
<?php foreach ($rooms as $room): ?>
    <?php
        $roomId = (string) ($room['id'] ?? '');
        if ($roomId === '') continue;
    ?>
    <div class="modal fade" id="roomEditModal-<?= e($roomId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Open the full edit form to update room <strong><?= e($room['roomNumber'] ?? 'this room') ?></strong>.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
