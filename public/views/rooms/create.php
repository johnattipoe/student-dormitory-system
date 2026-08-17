<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Create Room';
$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100);
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
            <h5 class="mb-3">Create Room</h5>
            <form method="POST" action="<?= url('views/rooms/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="roomNumber" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House</label>
                        <select name="houseId" class="form-select" required>
                            <option value="">Select house</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e($house['id'] ?? '') ?>"><?= e($house['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Save Room</button>
                    <a href="<?= url('views/rooms/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
