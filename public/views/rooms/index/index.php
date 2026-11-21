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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== ROLE_ADMIN) {
        http_response_code(403);
        exit('You are not authorized to manage rooms.');
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/rooms/index/index.php'));
    }

    $action = sanitize($_POST['action'] ?? '');
    if ($action === 'create_room') {
        $roomNumber = trim(sanitize($_POST['roomNumber'] ?? ''));
        $houseId = trim(sanitize($_POST['houseId'] ?? ''));
        try {
            if ($roomNumber === '' || !$houseId || !HouseService::find($houseId)) {
                flash('error', 'Enter a room number and select a valid house.');
            } else {
                RoomService::create([
                    'roomNumber' => $roomNumber,
                    'houseId' => $houseId,
                    'capacity' => max(1, (int) ($_POST['capacity'] ?? 1)),
                    'status' => in_array($_POST['status'] ?? '', ['available', 'occupied'], true)
                        ? $_POST['status']
                        : 'available',
                ]);
                flash('success', 'Room created successfully.');
            }
        } catch (Throwable $e) {
            error_log('Unable to create room: ' . $e->getMessage());
            flash('error', 'Unable to create room. Please try again.');
        }
    } elseif ($action === 'update_room') {
        $roomId = sanitize($_POST['id'] ?? '');
        if (trim(sanitize($_POST['roomNumber'] ?? '')) === '') {
            flash('error', 'Room number is required.');
        } else {
            try {
                $room = $roomId !== '' ? RoomService::find($roomId) : null;
                if (!$room) {
                    flash('error', 'Room not found.');
                } else {
                    RoomService::update($roomId, [
                        'roomNumber' => trim(sanitize($_POST['roomNumber'] ?? '')),
                        'status' => in_array($_POST['status'] ?? '', ['available', 'occupied'], true)
                            ? $_POST['status']
                            : 'available',
                    ]);
                    flash('success', 'Room updated successfully.');
                }
            } catch (Throwable $e) {
                error_log('Unable to update room: ' . $e->getMessage());
                flash('error', 'Unable to update room. Please try again.');
            }
        }
    }
    redirect(url('views/rooms/index/index.php'));
}

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
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoomModal"><i class="bi bi-plus-lg"></i> New Room</button>
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
<?php if (current_role() === ROLE_ADMIN): ?>
<div class="modal fade" id="createRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/rooms/index/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_room">
            <div class="modal-header"><h5 class="modal-title">Create Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Room Number</label><input name="roomNumber" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">House</label><select name="houseId" class="form-select" required><option value="">Select house</option><?php foreach ($houses as $house): ?><option value="<?= e((string) ($house['id'] ?? '')) ?>"><?= e($house['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" min="1" value="1" required></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="available">Available</option><option value="occupied">Occupied</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Room</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
<?php foreach ($rooms as $room): ?>
    <?php
        $roomId = (string) ($room['id'] ?? '');
        if ($roomId === '') continue;
    ?>
    <div class="modal fade" id="roomEditModal-<?= e($roomId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/rooms/index/index.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_room">
                    <input type="hidden" name="id" value="<?= e($roomId) ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit Room <?= e($room['roomNumber'] ?? '') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Room Number</label><input name="roomNumber" class="form-control" value="<?= e($room['roomNumber'] ?? '') ?>" required></div>
                        <div><label class="form-label">Status</label><select name="status" class="form-select"><option value="available" <?= ($room['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option><option value="occupied" <?= ($room['status'] ?? '') === 'occupied' ? 'selected' : '' ?>>Occupied</option></select></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Room</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
