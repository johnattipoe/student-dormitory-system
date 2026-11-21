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
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    if ($action === 'create_room') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            flash('error', 'The form expired. Please refresh the page and try again.');
            redirect(url('views/house-master/rooms/index/index.php'));
        }
        $roomNumber = trim(sanitize($_POST['roomNumber'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 1);
        $type = sanitize($_POST['type'] ?? 'standard');
        if ($roomNumber === '' || !$houseId || $capacity < 1) {
            flash('error', 'Room number and a positive capacity are required.');
        } else {
            try {
                RoomService::create([
                    'roomNumber' => $roomNumber,
                    'capacity' => $capacity,
                    'type' => $type,
                    'status' => sanitize($_POST['status'] ?? 'available'),
                    'houseId' => $houseId,
                ]);
                flash('success', 'Room created successfully.');
            } catch (Throwable $e) {
                error_log('Unable to create house room: ' . $e->getMessage());
                flash('error', 'Unable to create room. Please try again.');
            }
        }
        redirect(url('views/house-master/rooms/index/index.php'));
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allRooms = RoomService::all($houseId);
$students = StudentService::all($houseId);
$totalRooms = count($allRooms);
$totalPages = max(1, (int) ceil($totalRooms / $limit));
$page = min($page, $totalPages);
$rooms = array_slice($allRooms, ($page - 1) * $limit, $limit);
$roomStats = RoomService::occupancyStats($houseId);

$pageTitle = 'House Rooms';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php')],
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
                    <i class="bi bi-door-closed-fill text-info me-2"></i>House Room Blocks &amp; Allocations
                </h4>
                <p class="text-muted mb-0">Monitor room capacity, bed occupancy, and resident assignments in your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/rooms/allocation/allocation.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-diagram-3 me-1"></i> Allocation Matrix
                </a>
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#houseExportRoomsModal">
                    <i class="bi bi-filetype-csv me-1"></i> CSV
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#houseCreateRoomModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Room
                </button>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Rooms</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) ($roomStats['rooms'] ?? 0)) ?></h3>
                            <span class="small text-muted">Configured blocks</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-closed fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Bed Capacity</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) ($roomStats['capacity'] ?? 0)) ?></h3>
                            <span class="small text-muted">Total bed spaces</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupied Beds</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) ($roomStats['occupied'] ?? 0)) ?></h3>
                            <span class="small text-muted">Current residents</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupancy Rate</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</h3>
                            <span class="small text-muted">Capacity utilization</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-pie-chart fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-closed me-2 text-info"></i>House Room Registry</h6>
                <small class="text-muted">Showing <?= count($rooms) ?> of <?= e((string) $totalRooms) ?> rooms</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-no-copy-export="true" data-no-table-search="true">
                        <thead class="table-light">
                            <tr>
                                <th>Room Number</th>
                                <th>Bed Capacity</th>
                                <th>Occupied</th>
                                <th>Density</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rooms)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No rooms configured for this house.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $room): ?>
                                    <?php
                                    $rCap = (int) ($room['capacity'] ?? 0);
                                    $rOcc = (int) ($room['occupied'] ?? 0);
                                    $rRate = $rCap > 0 ? min(100, round(($rOcc / $rCap) * 100)) : 0;
                                    $rId = (string) ($room['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><strong class="text-dark">Room <?= e($room['roomNumber'] ?? '') ?></strong></td>
                                        <td><?= $rCap ?> beds</td>
                                        <td><span class="fw-semibold text-primary"><?= $rOcc ?> residents</span></td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-<?= $rRate >= 100 ? 'danger' : ($rRate >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $rRate ?>%;"></div>
                                                </div>
                                                <small class="text-muted"><?= $rRate ?>%</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= ($room['status'] ?? '') === 'available' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst(e($room['status'] ?? 'available')) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseRoomViewModal-<?= e($rId) ?>" title="View"><i class="bi bi-eye"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#houseRoomEditModal-<?= e($rId) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseRoomDeleteModal-<?= e($rId) ?>" title="Delete"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php $paginationBaseUrl = url('views/house-master/rooms/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>

    </div>
</div>
<?php foreach ($rooms as $room): ?>
    <?php $modalRoomId = (string) ($room['id'] ?? ''); $modalRoomStatus = (string) ($room['status'] ?? 'available'); ?>
    <div class="modal fade" id="houseRoomViewModal-<?= e($modalRoomId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Room Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Room</dt><dd class="col-sm-7"><?= e($room['roomNumber'] ?? '—') ?></dd><dt class="col-sm-5">Capacity</dt><dd class="col-sm-7"><?= e((string) ($room['capacity'] ?? 0)) ?> beds</dd><dt class="col-sm-5">Occupied</dt><dd class="col-sm-7"><?= e((string) ($room['occupied'] ?? 0)) ?> residents</dd><dt class="col-sm-5">Type</dt><dd class="col-sm-7"><?= e($room['type'] ?? 'standard') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst($modalRoomStatus)) ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="houseRoomEditModal-<?= e($modalRoomId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/rooms/edit/edit.php?id=' . urlencode($modalRoomId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalRoomId) ?>"><label class="form-label">Room Number</label><input name="roomNumber" class="form-control mb-3" value="<?= e($room['roomNumber'] ?? '') ?>" required><label class="form-label">Capacity</label><input type="number" min="1" name="capacity" class="form-control mb-3" value="<?= e((string) ($room['capacity'] ?? 1)) ?>" required><label class="form-label">Type</label><input name="type" class="form-control mb-3" value="<?= e($room['type'] ?? 'standard') ?>"><label class="form-label">Status</label><select name="status" class="form-select"><option value="available" <?= $modalRoomStatus === 'available' ? 'selected' : '' ?>>Available</option><option value="occupied" <?= $modalRoomStatus === 'occupied' ? 'selected' : '' ?>>Occupied</option><option value="maintenance" <?= $modalRoomStatus === 'maintenance' ? 'selected' : '' ?>>Maintenance</option></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="houseRoomDeleteModal-<?= e($modalRoomId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete room <strong><?= e($room['roomNumber'] ?? 'this room') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/rooms/delete/delete.php?id=' . urlencode($modalRoomId)) ?>"><input type="hidden" name="id" value="<?= e($modalRoomId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<div class="modal fade" id="houseExportRoomsModal" tabindex="-1" aria-labelledby="houseExportRoomsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="houseExportRoomsModalLabel">Export house rooms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Download a CSV containing room numbers, type, capacity, occupancy, and status for this house.
                <div id="houseExportRoomsError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="houseExportRoomsDownload" class="btn btn-success" data-export-url="<?= e(url('views/house-master/reports/export/export.php?type=rooms')) ?>" data-filename="house-rooms-<?= e(date('Y-m-d')) ?>.csv">
                    <i class="bi bi-download me-1"></i>Download CSV
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="houseCreateRoomModal" tabindex="-1" aria-labelledby="houseCreateRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/house-master/rooms/index/index.php') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="houseCreateRoomModalLabel">Add room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_room">
                    <label class="form-label" for="houseRoomNumber">Room number</label>
                    <input id="houseRoomNumber" name="roomNumber" class="form-control mb-3" placeholder="e.g. A101 or Room 5" required>
                    <label class="form-label" for="houseRoomCapacity">Bed capacity</label>
                    <input id="houseRoomCapacity" type="number" name="capacity" class="form-control mb-3" min="1" value="4" required>
                    <label class="form-label" for="houseRoomType">Room type</label>
                    <input id="houseRoomType" name="type" class="form-control mb-3" value="standard" placeholder="e.g. standard, prefect">
                    <label class="form-label" for="houseRoomStatus">Status</label>
                    <select id="houseRoomStatus" name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create room</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('#houseExportRoomsModal, #houseCreateRoomModal').forEach(function (modal) {
    modal.dataset.disableLoading = 'true';
    document.body.appendChild(modal);
});

document.getElementById('houseExportRoomsDownload').addEventListener('click', async function () {
    const button = this;
    const errorMessage = document.getElementById('houseExportRoomsError');
    SDS.showButtonLoading(button);
    errorMessage.classList.add('d-none');
    errorMessage.textContent = '';
    try {
        const response = await fetch(button.dataset.exportUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok || !(response.headers.get('content-type') || '').includes('text/csv')) {
            throw new Error('The room export could not be downloaded. Please try again.');
        }
        const downloadUrl = URL.createObjectURL(await response.blob());
        const downloadLink = document.createElement('a');
        downloadLink.href = downloadUrl;
        downloadLink.download = button.dataset.filename;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
    } catch (error) {
        errorMessage.textContent = error instanceof Error ? error.message : 'The room export could not be downloaded. Please try again.';
        errorMessage.classList.remove('d-none');
    } finally {
        SDS.hideButtonLoading(button);
    }
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
