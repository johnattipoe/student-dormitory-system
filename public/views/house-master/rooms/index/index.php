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
                <a href="<?= url('views/house-master/reports/export/export.php?type=rooms') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv me-1"></i> CSV
                </a>
                <a href="<?= url('views/house-master/rooms/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Room
                </a>
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

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <input name="search" class="form-control form-control-sm" placeholder="Search room number..." value="<?= e($roomSearch) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="available" <?= $roomStatus === 'available' ? 'selected' : '' ?>>Available (Open Beds)</option>
                            <option value="occupied" <?= $roomStatus === 'occupied' ? 'selected' : '' ?>>Occupied / Full</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-closed me-2 text-info"></i>House Room Registry</h6>
                <small class="text-muted">Showing <?= count($rooms) ?> rooms</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/rooms/view/view.php?id=' . urlencode($rId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/rooms/edit/edit.php?id=' . urlencode($rId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/rooms/delete/delete.php?id=' . urlencode($rId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
