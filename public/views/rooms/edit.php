<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\RoomService;

$pageTitle = 'Edit Room';
$id = $_GET['id'] ?? '';
$room = $id ? RoomService::find($id) : null;
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
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Edit Room</h5>
            <?php if ($room): ?>
                <form method="POST" action="<?= url('rooms/' . urlencode($id) . '/update') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="roomNumber" class="form-control" value="<?= e($room['roomNumber'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="available" <?= ($room['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="occupied" <?= ($room['status'] ?? '') === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">Update Room</button>
                        <a href="<?= url('views/rooms/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">No room selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
