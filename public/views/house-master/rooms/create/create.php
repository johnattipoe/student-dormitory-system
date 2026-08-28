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

use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = RoomService::create([
        'roomNumber' => sanitize($_POST['roomNumber'] ?? ''),
        'capacity' => max(1, (int) ($_POST['capacity'] ?? 1)),
        'type' => sanitize($_POST['type'] ?? 'standard'),
        'status' => 'available',
        'houseId' => $houseId,
    ]);
    flash('success', 'Room created successfully.');
    redirect(url('views/house-master/rooms/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Add Room';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-door-open-fill text-success me-2"></i>Add New Room</h4>
                <p class="text-muted mb-0">Register a new dormitory room to your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Rooms
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 760px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-closed me-2 text-primary"></i>Room Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/rooms/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Room Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
                                <input name="roomNumber" class="form-control" placeholder="e.g. A101 or Room 5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bed Capacity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-grid-1x2"></i></span>
                                <input type="number" min="1" name="capacity" class="form-control" value="4" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Room Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-house"></i></span>
                                <input name="type" class="form-control" value="standard" placeholder="e.g. standard, dormitory, prefect">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/rooms/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle me-1"></i>Create Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>