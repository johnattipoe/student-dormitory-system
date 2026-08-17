<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\RoomService;

$pageTitle = 'Rooms';
$rooms = RoomService::all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/rooms/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Rooms</h5>
            <?php if (current_role() === ROLE_ADMIN): ?>
                <a href="<?= url('views/rooms/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Room</a>
            <?php endif; ?>
        </div>

        <div class="card stat-card p-3">
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
                        <td><?= e($room['houseId'] ?? '-') ?></td>
                        <td><?= e((string) ($room['capacity'] ?? 0)) ?></td>
                        <td><?= e((string) ($room['occupied'] ?? 0)) ?></td>
                        <td><span class="badge bg-<?= ($room['status'] ?? 'available') === 'available' ? 'success' : 'secondary' ?>"><?= e($room['status'] ?? 'available') ?></span></td>
                        <td>
                            <?php if (current_role() === ROLE_ADMIN): ?>
                                <a href="<?= url('views/rooms/edit.php?id=' . urlencode($room['id'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php else: ?>
                                <span class="text-muted small">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
