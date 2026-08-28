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

$id = sanitize($_GET['id'] ?? '');
$room = $id ? RoomService::find($id) : null;
$houseId = current_user()['houseId'] ?? null;
if (!$room || ($room['houseId'] ?? null) !== $houseId) {
    flash('error', 'Room not found in your assigned house.');
    redirect(url('views/house-master/rooms/index/index.php'));
}

$students = array_values(array_filter(StudentService::all($houseId), fn($student) => ($student['roomId'] ?? '') === $id));
$capacity = (int) ($room['capacity'] ?? 0);
$occupied = count($students);
$available = max(0, $capacity - $occupied);
$occupancyPct = $capacity > 0 ? round(($occupied / $capacity) * 100) : 0;
$status = strtolower((string) ($room['status'] ?? 'available'));
$statusBadge = match($status) {
    'full' => 'bg-danger text-white',
    'maintenance' => 'bg-warning text-dark',
    default => 'bg-success text-white'
};

$pageTitle = 'Room Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-door-closed-fill text-primary me-2"></i>Room <?= e($room['roomNumber'] ?? '') ?></h4>
                <p class="text-muted mb-0">Dormitory room details and current bed occupancy</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-warning btn-sm" href="<?= url('views/house-master/rooms/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit Room
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= url('views/house-master/rooms/delete/delete.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-trash me-1"></i>Delete
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Capacity</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $capacity) ?></h3>
                            <span class="small text-muted">Total beds</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-grid-1x2 fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupied</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $occupied) ?></h3>
                            <span class="small text-muted">Students assigned</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $available) ?></h3>
                            <span class="small text-muted">Open beds</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-circle-half fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Status</span>
                            <h3 class="fw-bold my-1 text-info"><?= $occupancyPct ?>%</h3>
                            <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($status)) ?></span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-bar-chart fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Info + Occupants -->
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Room Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <span class="text-muted small d-block">Room Number</span>
                            <strong class="fs-5"><?= e($room['roomNumber'] ?? '—') ?></strong>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted small d-block">Room Type / Floor</span>
                            <strong><?= e($room['type'] ?? $room['floor'] ?? 'Standard') ?></strong>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted small d-block">Bed Capacity</span>
                            <strong><?= e((string) $capacity) ?> beds</strong>
                        </div>
                        <div class="mb-4">
                            <span class="text-muted small d-block">Occupancy</span>
                            <div class="progress mt-1" style="height: 8px;">
                                <div class="progress-bar bg-<?= $occupancyPct >= 100 ? 'danger' : ($occupancyPct >= 75 ? 'warning' : 'success') ?>"
                                     style="width: <?= $occupancyPct ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $occupancyPct ?>% full</small>
                        </div>
                        <a class="btn btn-outline-primary btn-sm w-100" href="<?= url('views/house-master/rooms/allocation/allocation.php?roomId=' . urlencode($id)) ?>">
                            <i class="bi bi-person-plus me-1"></i>Manage Allocation
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Current Occupants</h6>
                        <small class="text-muted"><?= count($students) ?> student(s)</small>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($students)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($students as $student): ?>
                                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 px-4"
                                       href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>">
                                        <div>
                                            <strong class="text-dark d-block"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>
                                            <small class="text-muted font-monospace"><?= e($student['admissionNo'] ?? 'No ID') ?></small>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-person-slash fs-3 d-block mb-2"></i>
                                No students currently assigned to this room.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>