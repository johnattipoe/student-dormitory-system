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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\RoomService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$rooms = RoomService::all($houseId);
$students = StudentService::all($houseId);
$roomSearch = strtolower(sanitize($_GET['search'] ?? ''));
$roomStatus = sanitize($_GET['status'] ?? '');
if ($roomSearch !== '' || $roomStatus !== '') {
    $rooms = array_values(array_filter($rooms, function ($room) use ($roomSearch, $roomStatus) {
        return ($roomSearch === '' || str_contains(strtolower((string) ($room['roomNumber'] ?? '')), $roomSearch))
            && ($roomStatus === '' || ($room['status'] ?? '') === $roomStatus);
    }));
}
$roomStats = RoomService::occupancyStats($houseId);

$pageTitle = 'House Master Rooms';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div><h5 class="mb-1">Room Allocation</h5><p class="text-muted mb-0">Manage room capacity and student occupancy.</p></div>
            <a href="<?= url('views/house-master/reports/export.php?type=rooms') ?>" class="btn btn-success btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
            <a href="<?= url('views/house-master/rooms/allocation.php') ?>" class="btn btn-info btn-sm"><i class="bi bi-grid-3x3-gap"></i> Allocation</a>
            <a href="<?= url('views/house-master/rooms/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add room</a>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Rooms</small><strong class="fs-3"><?= e((string) ($roomStats['rooms'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Capacity</small><strong class="fs-3"><?= e((string) ($roomStats['capacity'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupied</small><strong class="fs-3"><?= e((string) ($roomStats['occupied'] ?? 0)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupancy rate</small><strong class="fs-3"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</strong></div></div>
        </div>
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5"><input name="search" class="form-control form-control-sm" placeholder="Search room number" value="<?= e($roomSearch) ?>"></div>
                <div class="col-md-4"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="available" <?= $roomStatus === 'available' ? 'selected' : '' ?>>Available</option><option value="occupied" <?= $roomStatus === 'occupied' ? 'selected' : '' ?>>Occupied</option></select></div>
                <div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index.php') ?>">Reset</a></div>
            </form>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rooms)): ?>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><?= e($room['roomNumber'] ?? '') ?></td>
                                <td><?= e($room['capacity'] ?? '') ?></td>
                                <td><?= e($room['occupancy'] ?? '0') ?></td>
                                <td><span class="badge bg-<?= ($room['status'] ?? '') === 'available' ? 'success' : 'secondary' ?>"><?= e($room['status'] ?? 'unknown') ?></span></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/rooms/view.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/rooms/edit.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/rooms/delete.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No room data available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
