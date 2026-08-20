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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\RoomService;

$pageTitle = 'Rooms';
$rooms = RoomService::all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $rooms = array_values(array_filter($rooms, fn($room) => str_contains(strtolower((string) ($room['roomNumber'] ?? '')), $search) || str_contains(strtolower((string) ($room['houseId'] ?? '')), $search)));
}
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php'), 'active' => true],
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
            <div><a href="<?= url('views/admin/rooms/bulk-import.php') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel</a> <a href="<?= url('views/admin/rooms/allocation.php') ?>" class="btn btn-outline-primary btn-sm">Allocation</a> <a href="<?= url('views/admin/rooms/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Room</a></div>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Search room or house" value="<?= e($search) ?>"></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/rooms/index.php') ?>">Reset</a></div></form></div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Room</th>
                    <th>House</th>
                    <th>Capacity</th>
                    <th>Occupied</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?= e($room['roomNumber'] ?? '') ?></td>
                        <td><?= e($room['houseId'] ?? '') ?></td>
                        <td><?= e($room['capacity'] ?? '') ?></td>
                        <td><?= e($room['occupied'] ?? 0) ?></td>
                        <td><span class="badge bg-<?= ($room['status'] ?? '') === 'available' ? 'success' : 'secondary' ?>"><?= e($room['status'] ?? '') ?></span></td>
                        <td>
                            <a href="<?= url('views/admin/rooms/view.php?id=' . urlencode($room['id'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="<?= url('views/admin/rooms/edit.php?id=' . urlencode($room['id'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <a href="<?= url('views/admin/rooms/delete.php?id=' . urlencode($room['id'] ?? '')) ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>