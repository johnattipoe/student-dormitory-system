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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;
$rooms = RoomService::all($houseId);
$roomStats = RoomService::occupancyStats($houseId);
$roomSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($roomSearch !== '') {
    $rooms = array_values(array_filter($rooms, fn($room) => str_contains(strtolower((string) ($room['roomNumber'] ?? '')), $roomSearch)));
}

$pageTitle = 'Houseparent Rooms';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">House Rooms</h5>
            <div><a href="<?= url('views/houseparent/reports/occupancy.php') ?>" class="btn btn-outline-primary btn-sm">Occupancy report</a> <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e((string) $roomStats['rooms']) ?> rooms</span></div>
        </div>

        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-8"><input name="search" class="form-control form-control-sm" placeholder="Search room number" value="<?= e($roomSearch) ?>"></div><div class="col-md-4"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/rooms/index.php') ?>">Reset</a></div></form></div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Rooms</div>
                    <div class="fs-2 fw-bold"><?= e((string) $roomStats['rooms']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Capacity</div>
                    <div class="fs-2 fw-bold"><?= e((string) $roomStats['capacity']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Occupied</div>
                    <div class="fs-2 fw-bold"><?= e((string) $roomStats['occupied']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Occupancy</div>
                    <div class="fs-2 fw-bold"><?= e((string) $roomStats['occupancyRate']) ?>%</div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Vacant</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rooms)): ?>
                        <?php foreach ($rooms as $room): ?>
                            <?php $occupied = (int) ($room['occupied'] ?? 0); $capacity = (int) ($room['capacity'] ?? 0); $vacant = max(0, $capacity - $occupied); ?>
                            <tr>
                                <td><?= e($room['roomNumber'] ?? '') ?></td>
                                <td><?= e((string) $capacity) ?></td>
                                <td><?= e((string) $occupied) ?></td>
                                <td><?= e((string) $vacant) ?></td>
                                <td><span class="badge bg-<?= ($occupied >= $capacity) ? 'secondary' : 'success' ?>"><?= e((string) ($room['status'] ?? ($occupied >= $capacity ? 'full' : 'available'))) ?></span></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/houseparent/rooms/view.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/houseparent/rooms/edit.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/houseparent/rooms/delete.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No room data available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>