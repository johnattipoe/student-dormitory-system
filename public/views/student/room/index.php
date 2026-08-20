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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\RoomService;
use App\Services\StudentService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$student = null;
if ($studentId) {
    $student = StudentService::find((string) $studentId);
}

if (!$student && !empty(current_user()['email'])) {
    foreach (StudentService::all() as $s) {
        if ((string) ($s['email'] ?? '') === (string) current_user()['email']) {
            $student = $s;
            break;
        }
    }
}

$roomId = null;
if (($student['roomId'] ?? null)) {
    $roomId = (string) $student['roomId'];
}

if (!$roomId && $studentId) {
    $allocations = FirebaseService::getInstance()->where(COL_ROOM_ALLOCATIONS, 'studentId', '=', (string) $studentId, 20);
    foreach ($allocations as $allocation) {
        if (($allocation['status'] ?? 'active') === 'active' || empty($allocation['status'])) {
            $roomId = (string) ($allocation['roomId'] ?? '');
            break;
        }
    }
}

$room = $roomId ? RoomService::find((string) $roomId) : [];
$roomNumber = $room['roomNumber'] ?? '—';
$block = $room['block'] ?? $room['blockName'] ?? '—';
$capacity = $room['capacity'] ?? 0;
$occupied = (int) ($room['occupied'] ?? 0);
$occupancyRate = $capacity > 0 ? round(($occupied / $capacity) * 100) : 0;

$pageTitle = 'Student Room';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php'), 'active' => true],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-3">
            <h5 class="mb-1">Room Information</h5>
            <p class="text-muted mb-0">Your current accommodation details and room status.</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Room Number</div>
                    <div class="fs-3 fw-bold"><?= e((string) $roomNumber) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Block</div>
                    <div class="fs-3 fw-bold"><?= e((string) $block) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Capacity</div>
                    <div class="fs-3 fw-bold"><?= e((string) $capacity) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Occupancy</div>
                    <div class="fs-3 fw-bold"><?= e((string) $occupancyRate) ?>%</div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="row g-3">
                <div class="col-md-6"><strong>Room:</strong> <?= e((string) $roomNumber) ?></div>
                <div class="col-md-6"><strong>Block:</strong> <?= e((string) $block) ?></div>
                <div class="col-md-6"><strong>Capacity:</strong> <?= e((string) $capacity) ?></div>
                <div class="col-md-6"><strong>Occupied:</strong> <?= e((string) $occupied) ?></div>
                <div class="col-md-6"><strong>Status:</strong> <?= e((string) ($room['status'] ?? 'unknown')) ?></div>
                <div class="col-md-6"><strong>House:</strong> <?= e((string) ($student['houseId'] ?? $room['houseId'] ?? '—')) ?></div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
