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
use App\Services\StudentService;
use App\Services\HouseService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$rooms = RoomService::all($houseId);
$students = StudentService::all($houseId);
$stats = RoomService::occupancyStats($houseId);

$pageTitle = 'Room & Bed Occupancy Report';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/senior-houseparent/reports/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Room & Bed Occupancy Report (<?= e($houseName) ?>)</h5>
                <p class="text-muted mb-0">Dormitory capacity, allocation metrics, and vacant bed tracking.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/senior-houseparent/reports/export/export.php?type=occupancy') ?>">
                    <i class="bi bi-filetype-csv me-1"></i> Export Occupancy CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/reports/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Reports Overview
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['Total Rooms', $stats['rooms'] ?? 0, 'primary'],
                ['Total Bed Capacity', $stats['capacity'] ?? 0, 'info'],
                ['Occupied Beds', $stats['occupied'] ?? 0, 'warning'],
                ['Vacant / Available Beds', $stats['vacant'] ?? 0, 'success'],
            ] as [$label, $value, $color]): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <small class="text-muted"><?= e($label) ?></small>
                        <strong class="fs-2 text-<?= e($color) ?>"><?= e((string) $value) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold">Dormitory Rooms List</h6>
                <a class="btn btn-sm btn-primary" href="<?= url('views/senior-houseparent/rooms/index/index.php') ?>">
                    <i class="bi bi-door-open me-1"></i> Room Management
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Room Number / Name</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Available Beds</th>
                            <th>Occupancy Meter</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No rooms configured for this house.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rooms as $room): ?>
                                <?php 
                                    $capacity = (int) ($room['capacity'] ?? 0);
                                    $occupied = (int) ($room['occupied'] ?? $room['occupancy'] ?? 0);
                                    $available = max(0, $capacity - $occupied);
                                    $rate = $capacity > 0 ? round(($occupied / $capacity) * 100) : 0;
                                    $status = (string) ($room['status'] ?? 'active');
                                ?>
                                <tr>
                                    <td><strong><?= e($room['roomNumber'] ?? $room['name'] ?? '—') ?></strong></td>
                                    <td><?= e(ucfirst((string) ($room['type'] ?? 'standard'))) ?></td>
                                    <td><?= e((string) $capacity) ?></td>
                                    <td><strong><?= e((string) $occupied) ?></strong></td>
                                    <td><strong class="text-success"><?= e((string) $available) ?></strong></td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-<?= $rate >= 100 ? 'danger' : ($rate >= 75 ? 'warning' : 'primary') ?>" style="width: <?= e((string) min(100, $rate)) ?>%;"></div>
                                            </div>
                                            <span class="small text-muted"><?= e((string) $rate) ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($status)) ?>
                                        </span>
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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>