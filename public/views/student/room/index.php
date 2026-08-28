<?php
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

$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\FirebaseService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$student = null;
$room = [];
$house = null;
$roomBeds = [];
$roommates = [];
$activeAllocation = null;
$pageNotice = null;

try {
    if ($studentId) {
        $student = StudentService::find((string) $studentId);
    }

    if (!$student && !empty($currentUser['email'])) {
        foreach (StudentService::all() as $candidate) {
            if ((string) ($candidate['email'] ?? '') === (string) $currentUser['email']) {
                $student = $candidate;
                $studentId = $candidate['id'] ?? $studentId;
                break;
            }
        }
    }

    $roomId = (string) ($student['roomId'] ?? '');
    if ($roomId === '' && $studentId) {
        $allocations = FirebaseService::getInstance()->where(COL_ROOM_ALLOCATIONS, 'studentId', '=', (string) $studentId, 20);
        foreach ($allocations as $allocation) {
            if (($allocation['status'] ?? 'active') === 'active' || empty($allocation['status'])) {
                $roomId = (string) ($allocation['roomId'] ?? '');
                $activeAllocation = $allocation;
                break;
            }
        }
    }

    if ($roomId !== '') {
        $room = RoomService::find($roomId) ?? [];
        foreach (BedService::all() as $bed) {
            if ((string) ($bed['roomId'] ?? '') === $roomId) {
                $roomBeds[] = $bed;
            }
        }

        foreach (StudentService::all() as $candidate) {
            if ((string) ($candidate['roomId'] ?? '') === $roomId) {
                $roommates[] = $candidate;
            }
        }
    }

    $houseId = (string) ($student['houseId'] ?? $room['houseId'] ?? '');
    if ($houseId !== '') {
        $house = HouseService::find($houseId);
    }
} catch (Throwable $e) {
    $pageNotice = 'Room information is temporarily unavailable. Please try again later.';
}

$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') {
    $studentName = $currentUser['name'] ?? 'Student';
}

$roomNumber = (string) ($room['roomNumber'] ?? 'Not assigned');
$block = (string) ($room['block'] ?? $room['blockName'] ?? 'Not specified');
$roomType = (string) ($room['type'] ?? $room['roomType'] ?? 'Standard');
$capacity = (int) ($room['capacity'] ?? count($roomBeds) ?: 0);
$occupied = (int) ($room['occupied'] ?? count($roommates));
$available = max(0, $capacity - $occupied);
$occupancyRate = $capacity > 0 ? min(100, round(($occupied / $capacity) * 100)) : 0;
$roomStatus = ($room['status'] ?? '') === 'maintenance'
    ? 'maintenance'
    : ($capacity > 0 && $occupied >= $capacity ? 'full' : ($occupied > 0 ? 'occupied' : 'available'));
$statusClass = match ($roomStatus) {
    'available' => 'success',
    'occupied' => 'primary',
    'full' => 'warning text-dark',
    'maintenance' => 'secondary',
    default => 'info',
};
$houseName = (string) ($house['name'] ?? $house['houseName'] ?? 'Not assigned');
$houseGender = (string) ($house['gender'] ?? 'Not specified');
$houseMaster = (string) ($house['houseMasterName'] ?? $house['masterName'] ?? $house['wardenName'] ?? 'Not specified');

$pageTitle = 'Student Room';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php'), 'active' => true],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/student/beds/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-house-door-fill text-primary me-2"></i>My Dormitory Room</h4>
                <p class="text-muted mb-0">Room <?= e($roomNumber) ?> &bull; <?= e($houseName) ?> &bull; <?= e($studentName) ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/student/beds/index.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-grid-3x3-gap me-1"></i>My Bed
                </a>
                <a href="<?= url('views/student/incidents/create/create.php') ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-flag me-1"></i>Report Room Issue
                </a>
            </div>
        </div>

        <?php if ($pageNotice): ?>
            <div class="alert alert-warning mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($pageNotice) ?></div>
        <?php endif; ?>

        <?php if (!$student || empty($room)): ?>
            <div class="card stat-card shadow-sm border-0 text-center py-5">
                <div class="card-body">
                    <div class="rounded-circle bg-light d-inline-flex p-3 mb-3 text-muted">
                        <i class="bi bi-house-slash fs-1"></i>
                    </div>
                    <h5 class="fw-bold">No Room Assigned Yet</h5>
                    <p class="text-muted mb-4">Your room allocation is not available yet. Please check again later or contact your House Master.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= url('views/student/dashboard/index.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                        <a href="<?= url('views/student/profile/index/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-circle me-1"></i>View Profile</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- KPI Stats -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Room Number</span>
                                <h3 class="fw-bold my-1 text-primary"><?= e($roomNumber) ?></h3>
                                <span class="small text-muted"><?= e(ucfirst($roomType)) ?></span>
                            </div>
                            <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-door-open fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Block / Floor</span>
                                <h3 class="fw-bold my-1 text-info"><?= e($block) ?></h3>
                                <span class="small text-muted"><?= e($houseName) ?></span>
                            </div>
                            <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-building fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Occupancy</span>
                                <h3 class="fw-bold my-1 text-warning"><?= e((string) $occupied) ?> / <?= e((string) $capacity) ?></h3>
                                <span class="small text-muted"><?= e((string) $occupancyRate) ?>% full</span>
                            </div>
                            <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-people fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Spaces Left</span>
                                <h3 class="fw-bold my-1 text-success"><?= e((string) $available) ?></h3>
                                <span class="small text-muted">Vacant spots</span>
                            </div>
                            <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check2-circle fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- Room Details Card -->
                    <div class="card stat-card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Room Specifications</h6>
                            <span class="badge bg-<?= e($statusClass) ?>"><?= e(ucfirst($roomStatus)) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Capacity Occupancy</span>
                                    <span class="fw-bold"><?= e((string) $occupancyRate) ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?= e((string) $occupancyRate) ?>%"></div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Room Number</span>
                                    <strong><?= e($roomNumber) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Room Type</span>
                                    <strong><?= e(ucfirst($roomType)) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Block</span>
                                    <strong><?= e($block) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">House</span>
                                    <strong><?= e($houseName) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Total Capacity</span>
                                    <strong><?= e((string) $capacity) ?> Students</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Available Spaces</span>
                                    <strong class="text-success"><?= e((string) $available) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Roommates Card -->
                    <div class="card stat-card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Roommates (<?= count($roommates) ?>)</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($roommates): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($roommates as $mate): ?>
                                        <?php
                                        $mateName = trim(($mate['firstName'] ?? '') . ' ' . ($mate['lastName'] ?? '')) ?: 'Student';
                                        $isCurrentStudent = (string) ($mate['id'] ?? '') === (string) ($student['id'] ?? $studentId ?? '');
                                        $initials = strtoupper(substr((string) ($mate['firstName'] ?? $mateName), 0, 1) . substr((string) ($mate['lastName'] ?? ''), 0, 1));
                                        ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 <?= $isCurrentStudent ? 'bg-light' : '' ?>">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">
                                                    <?= e($initials ?: 'S') ?>
                                                </div>
                                                <div>
                                                    <strong class="d-block text-dark"><?= e($mateName) ?></strong>
                                                    <small class="text-muted"><?= e($mate['admissionNo'] ?? 'No admission number') ?></small>
                                                </div>
                                            </div>
                                            <?php if ($isCurrentStudent): ?>
                                                <span class="badge bg-primary">You</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted p-4 mb-0 text-center">No roommate records found for this room.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- House Information -->
                    <div class="card stat-card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>House Overview</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted">House</span>
                                    <strong><?= e($houseName) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted">House Master</span>
                                    <strong><?= e($houseMaster) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted">Gender</span>
                                    <strong><?= e(ucfirst($houseGender)) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted">Status</span>
                                    <span class="badge bg-success">Active Allocation</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Beds in Room -->
                    <div class="card stat-card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Beds Layout</h6>
                            <a href="<?= url('views/student/beds/index.php') ?>" class="small text-decoration-none">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($roomBeds): ?>
                                <div class="row g-2">
                                    <?php foreach ($roomBeds as $bed): ?>
                                        <?php
                                        $occupiedBy = !empty($bed['studentId']);
                                        $isMine = (string) ($bed['studentId'] ?? '') === (string) ($student['id'] ?? $studentId ?? '');
                                        ?>
                                        <div class="col-6">
                                            <div class="card p-2 text-center border <?= $isMine ? 'border-primary bg-primary bg-opacity-10' : ($occupiedBy ? 'border-secondary-subtle bg-light' : 'border-success-subtle bg-success bg-opacity-10') ?>">
                                                <i class="bi bi-layout-text-window-reverse fs-5 mb-1 <?= $isMine ? 'text-primary' : ($occupiedBy ? 'text-muted' : 'text-success') ?>"></i>
                                                <strong class="small d-block"><?= e($bed['bedNumber'] ?? '-') ?></strong>
                                                <span class="badge bg-<?= $isMine ? 'primary' : ($occupiedBy ? 'secondary' : 'success') ?> mt-1" style="font-size: 0.65rem;">
                                                    <?= $isMine ? 'Yours' : ($occupiedBy ? 'Occupied' : 'Open') ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0 text-center">No bed records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
