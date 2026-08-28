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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;
$rooms = RoomService::all($houseId);
$roomStats = RoomService::occupancyStats($houseId);
$roomSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($roomSearch !== '') {
    $rooms = array_values(array_filter($rooms, fn($room) => str_contains(strtolower((string) ($room['roomNumber'] ?? '')), $roomSearch)));
}

$pageTitle = 'Senior Houseparent Rooms';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-door-open-fill text-info me-2"></i>House Rooms</h4>
                <p class="text-muted mb-0">Room allocations and occupancy overview</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="<?= url('views/senior-houseparent/reports/occupancy/occupancy.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>Occupancy Report
                </a>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e((string) $roomStats['rooms']) ?> rooms</span>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Rooms</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) ($roomStats['rooms'] ?? 0)) ?></h3>
                            <span class="small text-muted">Active rooms</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-door-closed fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Capacity</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) ($roomStats['capacity'] ?? 0)) ?></h3>
                            <span class="small text-muted">Total beds</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-grid fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupied</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) ($roomStats['occupied'] ?? 0)) ?></h3>
                            <span class="small text-muted">Allocated beds</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupancy Rate</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</h3>
                            <span class="small text-muted">Utilization</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-bar-chart fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search room number..." value="<?= e($roomSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/rooms/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-open me-2"></i>Room Directory</h6>
                <small class="text-muted">Showing <?= e((string) count($rooms)) ?> rooms</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
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
                                    <td class="fw-medium"><?= e($room['roomNumber'] ?? '') ?></td>
                                    <td><?= e((string) $capacity) ?></td>
                                    <td><?= e((string) $occupied) ?></td>
                                    <td><?= e((string) $vacant) ?></td>
                                    <td><span class="badge bg-<?= ($occupied >= $capacity) ? 'secondary' : 'success' ?>"><?= e((string) ($room['status'] ?? ($occupied >= $capacity ? 'full' : 'available'))) ?></span></td>
                                    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/rooms/view/view.php?id=' . urlencode((string) ($room['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No room data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>