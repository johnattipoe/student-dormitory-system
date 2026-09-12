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

$pageTitle = 'Rooms';
$rooms = RoomService::all();
$houses = HouseService::all();
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
}

$totalRooms = count($rooms);
$totalCapacity = array_sum(array_map(fn($r) => (int) ($r['capacity'] ?? 0), $rooms));
$totalOccupied = array_sum(array_map(fn($r) => (int) ($r['occupied'] ?? 0), $rooms));
$totalVacant = max(0, $totalCapacity - $totalOccupied);

$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $rooms = array_values(array_filter($rooms, function ($room) use ($search, $houseMap) {
        $houseId = (string) ($room['houseId'] ?? '');
        $houseName = $houseMap[$houseId] ?? $houseId;
        return str_contains(strtolower((string) ($room['roomNumber'] ?? '')), $search)
            || str_contains(strtolower($houseName), $search)
            || str_contains(strtolower($houseId), $search);
    }));
}

if (isset($_GET['created'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Room created.'];
if (isset($_GET['updated'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Room updated.'];
if (isset($_GET['deleted'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Room deleted.'];

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
                    <i class="bi bi-door-closed-fill text-info me-2"></i>Dormitory Rooms
                </h4>
                <p class="text-muted mb-0">Manage room blocks, capacities, and bed space allocations</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/rooms/bulk-import/bulk-import.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk Import
                </a>
                <a href="<?= url('views/admin/rooms/allocation/allocation.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-diagram-3 me-1"></i> Space Allocation
                </a>
                <a href="<?= url('views/admin/rooms/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Room
                </a>
            </div>
        </div>

        <!-- KPI Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Rooms</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $totalRooms) ?></h3>
                            <span class="small text-muted">Across all houses</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-closed fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Beds</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCapacity) ?></h3>
                            <span class="small text-muted">Capacity pool</span>
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
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $totalOccupied) ?></h3>
                            <span class="small text-muted"><?= $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100) : 0 ?>% occupancy rate</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Vacant Beds</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $totalVacant) ?></h3>
                            <span class="small text-muted">Available spaces</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" class="form-control form-control-sm border-start-0" placeholder="Search by room number or dormitory house..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/rooms/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-door-closed me-2 text-info"></i>Room Directory</h6>
                    <small class="text-muted">Showing <?= count($rooms) ?> configured dormitory rooms</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Room No.</th>
                            <th>House</th>
                            <th>Capacity</th>
                            <th>Occupancy</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-door-closed fs-3 d-block text-secondary mb-1"></i>
                                    No rooms found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rooms as $room): ?>
                                <?php
                                $rCap = (int) ($room['capacity'] ?? 0);
                                $rOcc = (int) ($room['occupied'] ?? 0);
                                $rRate = $rCap > 0 ? min(100, round(($rOcc / $rCap) * 100)) : 0;
                                $rStatus = strtolower((string) ($room['status'] ?? ($rOcc >= $rCap && $rCap > 0 ? 'full' : 'available')));
                                $rBadge = match($rStatus) {
                                    'available' => 'bg-success',
                                    'full' => 'bg-danger',
                                    'maintenance' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                                $rId = (string) ($room['id'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark">Room <?= e($room['roomNumber'] ?? '') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-building me-1"></i><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '—')) ?>
                                        </span>
                                    </td>
                                    <td><?= $rCap ?> beds</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2" style="min-width: 120px;">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-<?= $rRate >= 100 ? 'danger' : ($rRate >= 75 ? 'warning' : 'primary') ?>" style="width: <?= $rRate ?>%;"></div>
                                            </div>
                                            <small class="text-muted"><?= $rOcc ?>/<?= $rCap ?></small>
                                        </div>
                                    </td>
                                    <td><span class="badge <?= $rBadge ?>"><?= ucfirst(e($rStatus)) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= url('views/admin/rooms/view/view.php?id=' . urlencode($rId)) ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url('views/admin/rooms/edit/edit.php?id=' . urlencode($rId)) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-sm btn-outline-danger" data-confirm
                                                data-action="<?= url('views/admin/rooms/delete/delete.php?id=' . urlencode($rId)) ?>"
                                                data-message="Delete Room <?= e($room['roomNumber'] ?? '') ?>? This cannot be undone." title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<?php require APP_ROOT . '/app/views/components/modal/modal.php'; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>