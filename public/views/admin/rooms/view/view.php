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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$id = sanitize($_GET['id'] ?? '');
$room = $id ? RoomService::find($id) : null;
if (!$room) {
    flash('error', 'Room not found.');
    redirect(url('views/admin/rooms/index/index.php'));
}

$houseId = (string) ($room['houseId'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = (string) ($house['name'] ?? ($houseId !== '' ? $houseId : '—'));
$students = StudentService::forRoom($id, 150, $houseId);

$capacity = (int) ($room['capacity'] ?? 0);
$occupied = (int) ($room['occupied'] ?? count($students));
$occupancyRate = $capacity > 0 ? min(100, round(($occupied / $capacity) * 100)) : 0;

$pageTitle = 'Room Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-door-open-fill text-info me-2"></i>Room <?= e($room['roomNumber'] ?? '') ?>
                </h4>
                <p class="text-muted mb-0">
                    House: <strong><?= e($houseName) ?></strong> &bull; Capacity: <strong><?= $capacity ?> beds</strong> &bull; Occupancy: <strong><?= $occupancyRate ?>%</strong>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditRoomModal">
                    <i class="bi bi-pencil me-1"></i> Edit Room
                </button>
                <a href="<?= url('views/admin/rooms/allocation/allocation.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-diagram-3 me-1"></i> Allocations
                </a>
                <a href="<?= url('views/admin/rooms/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Rooms
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Room Specs -->
            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Room Specifications</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Room Number</span>
                                <strong class="text-dark">Room <?= e($room['roomNumber'] ?? '') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Dormitory House</span>
                                <span><?= e($houseName) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Total Bed Capacity</span>
                                <span class="fw-bold text-primary"><?= $capacity ?> Beds</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Currently Occupied</span>
                                <span class="fw-bold text-success"><?= $occupied ?> Beds</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Vacant Space</span>
                                <span class="fw-bold text-warning"><?= max(0, $capacity - $occupied) ?> Beds</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Current Occupants -->
            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-success"></i>Current Occupants (<?= count($students) ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($students)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i>
                                No students currently assigned to this room.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($students as $st): ?>
                                    <?php
                                    $stName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')) ?: 'Student';
                                    $stId = (string) ($st['id'] ?? '');
                                    ?>
                                    <div class="list-group-item py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-dark d-block"><?= e($stName) ?></strong>
                                            <small class="text-muted font-monospace">Adm: <?= e($st['admissionNo'] ?? '—') ?></small>
                                        </div>
                                        <a href="<?= url('views/admin/students/view/view.php?id=' . urlencode($stId)) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Profile
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<div class="modal fade" id="adminEditRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/rooms/edit/edit.php?id=' . urlencode($id)) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Room Number</label><input name="roomNumber" class="form-control" value="<?= e($room['roomNumber'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Dormitory House</label><select name="houseId" class="form-select" required><?php foreach ($houses as $house): ?><option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= $houseId === (string) ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Bed Capacity</label><input type="number" name="capacity" min="1" class="form-control" value="<?= e((string) ($room['capacity'] ?? 1)) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Room Type</label><?php $roomType = (string) ($room['type'] ?? 'standard'); ?><select name="type" class="form-select"><option value="standard" <?= $roomType === 'standard' ? 'selected' : '' ?>>Standard Dorm</option><option value="prefect" <?= $roomType === 'prefect' ? 'selected' : '' ?>>Prefect Room</option><option value="sickbay" <?= $roomType === 'sickbay' ? 'selected' : '' ?>>Sickbay Annex</option><option value="special" <?= $roomType === 'special' ? 'selected' : '' ?>>Special Needs</option></select></div>
                    <div class="col-12"><label class="form-label">Status</label><?php $roomStatus = (string) ($room['status'] ?? 'available'); ?><select name="status" class="form-select"><option value="available" <?= $roomStatus === 'available' ? 'selected' : '' ?>>Available (Open)</option><option value="full" <?= $roomStatus === 'full' ? 'selected' : '' ?>>Full (Max Capacity)</option><option value="maintenance" <?= $roomStatus === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>