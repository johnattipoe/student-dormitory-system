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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
    'full' => 'warning',
    'maintenance' => 'secondary',
    default => 'info',
};
$houseName = (string) ($house['name'] ?? $house['houseName'] ?? 'Not assigned');
$houseGender = (string) ($house['gender'] ?? 'Not specified');
$houseMaster = (string) ($house['houseMasterName'] ?? $house['masterName'] ?? $house['wardenName'] ?? 'Not specified');

$pageTitle = 'Student Room';
$pageStyles = ['student.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php'), 'active' => true],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/student/beds/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="student-room-hero mb-4">
            <div class="student-room-hero-icon"><i class="bi bi-house-door"></i></div>
            <div>
                <span class="student-settings-kicker">Residence overview</span>
                <h1>Room <?= e($roomNumber) ?></h1>
                <p>Your current room, house, occupancy, roommates, and bed information in one place.</p>
                <div class="student-settings-badges">
                    <span class="badge bg-<?= e($statusClass) ?>"><i class="bi bi-circle-fill me-1"></i><?= e(ucfirst($roomStatus)) ?></span>
                    <span class="badge bg-info"><i class="bi bi-building me-1"></i><?= e($houseName) ?></span>
                    <span class="badge bg-primary"><i class="bi bi-person me-1"></i><?= e($studentName) ?></span>
                </div>
            </div>
            <div class="student-room-actions">
                <a href="<?= url('views/student/beds/index.php') ?>" class="btn btn-light"><i class="bi bi-grid-3x3-gap me-1"></i>My bed</a>
                <a href="<?= url('views/student/incidents/create.php') ?>" class="btn btn-primary"><i class="bi bi-flag me-1"></i>Report issue</a>
            </div>
        </section>

        <?php if ($pageNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($pageNotice) ?></div>
        <?php endif; ?>

        <?php if (!$student || empty($room)): ?>
            <div class="student-room-empty">
                <div class="student-room-empty-icon"><i class="bi bi-house-slash"></i></div>
                <h2>No room assigned yet</h2>
                <p>Your room allocation is not available yet. Please check again later or contact your house parent/admin office.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= url('views/student/settings/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-gear me-1"></i>Open settings</a>
                    <a href="<?= url('views/student/profile/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-person-circle me-1"></i>View profile</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="student-room-stat"><span class="student-room-stat-icon blue"><i class="bi bi-door-open"></i></span><div><small>Room number</small><strong><?= e($roomNumber) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-room-stat"><span class="student-room-stat-icon green"><i class="bi bi-diagram-3"></i></span><div><small>Block</small><strong><?= e($block) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-room-stat"><span class="student-room-stat-icon orange"><i class="bi bi-people"></i></span><div><small>Occupied</small><strong><?= e((string) $occupied) ?> / <?= e((string) $capacity) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-room-stat"><span class="student-room-stat-icon purple"><i class="bi bi-check2-circle"></i></span><div><small>Spaces left</small><strong><?= e((string) $available) ?></strong></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="student-room-main-card mb-4">
                        <div class="student-room-card-header">
                            <div>
                                <span class="student-settings-kicker">Room details</span>
                                <h2>Room <?= e($roomNumber) ?> summary</h2>
                                <p><?= e($roomType) ?> room in <?= e($houseName) ?>.</p>
                            </div>
                            <strong class="student-room-rate"><?= e((string) $occupancyRate) ?>%</strong>
                        </div>
                        <div class="progress student-room-progress mb-4" role="progressbar" aria-valuenow="<?= e((string) $occupancyRate) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= e((string) $occupancyRate) ?>%"></div>
                        </div>

                        <dl class="student-room-details">
                            <div><dt>Room number</dt><dd><?= e($roomNumber) ?></dd></div>
                            <div><dt>Room type</dt><dd><?= e(ucfirst($roomType)) ?></dd></div>
                            <div><dt>Block</dt><dd><?= e($block) ?></dd></div>
                            <div><dt>Status</dt><dd><?= e(ucfirst($roomStatus)) ?></dd></div>
                            <div><dt>Capacity</dt><dd><?= e((string) $capacity) ?> students</dd></div>
                            <div><dt>Available spaces</dt><dd><?= e((string) $available) ?></dd></div>
                            <div><dt>House</dt><dd><?= e($houseName) ?></dd></div>
                            <div><dt>House gender</dt><dd><?= e($houseGender) ?></dd></div>
                        </dl>
                    </div>

                    <div class="student-room-main-card">
                        <div class="student-room-card-header">
                            <div>
                                <span class="student-settings-kicker">Roommates</span>
                                <h2>Students in this room</h2>
                                <p><?= e((string) count($roommates)) ?> student<?= count($roommates) === 1 ? '' : 's' ?> currently linked to this room.</p>
                            </div>
                        </div>

                        <?php if ($roommates): ?>
                            <div class="student-roommates-grid">
                                <?php foreach ($roommates as $mate): ?>
                                    <?php
                                    $mateName = trim(($mate['firstName'] ?? '') . ' ' . ($mate['lastName'] ?? '')) ?: 'Student';
                                    $isCurrentStudent = (string) ($mate['id'] ?? '') === (string) ($student['id'] ?? $studentId ?? '');
                                    $initials = strtoupper(substr((string) ($mate['firstName'] ?? $mateName), 0, 1) . substr((string) ($mate['lastName'] ?? ''), 0, 1));
                                    ?>
                                    <div class="student-roommate-card <?= $isCurrentStudent ? 'is-current' : '' ?>">
                                        <div class="student-roommate-avatar"><?= e($initials ?: 'S') ?></div>
                                        <div>
                                            <strong><?= e($mateName) ?></strong>
                                            <span><?= e($mate['admissionNo'] ?? 'Admission not set') ?></span>
                                            <?php if ($isCurrentStudent): ?><small>Your account</small><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No roommate records were found for this room.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-xl-4">
                    <aside class="student-room-side-card mb-4">
                        <span class="student-settings-kicker">House information</span>
                        <h2><?= e($houseName) ?></h2>
                        <div class="student-room-info-list">
                            <div><i class="bi bi-building text-primary"></i><span>House</span><strong><?= e($houseName) ?></strong></div>
                            <div><i class="bi bi-person-badge text-success"></i><span>House master</span><strong><?= e($houseMaster) ?></strong></div>
                            <div><i class="bi bi-gender-ambiguous text-info"></i><span>House gender</span><strong><?= e($houseGender) ?></strong></div>
                            <div><i class="bi bi-clock-history text-warning"></i><span>Allocation</span><strong><?= e($activeAllocation['status'] ?? 'Active') ?></strong></div>
                        </div>
                    </aside>

                    <aside class="student-room-side-card">
                        <span class="student-settings-kicker">Beds</span>
                        <h2>Bed layout</h2>
                        <?php if ($roomBeds): ?>
                            <div class="student-room-bed-grid">
                                <?php foreach ($roomBeds as $bed): ?>
                                    <?php
                                    $occupiedBy = !empty($bed['studentId']);
                                    $isMine = (string) ($bed['studentId'] ?? '') === (string) ($student['id'] ?? $studentId ?? '');
                                    ?>
                                    <div class="student-room-bed <?= $isMine ? 'is-mine' : ($occupiedBy ? 'is-occupied' : 'is-open') ?>">
                                        <i class="bi bi-bed"></i>
                                        <strong><?= e($bed['bedNumber'] ?? '-') ?></strong>
                                        <span><?= $isMine ? 'Yours' : ($occupiedBy ? 'Occupied' : 'Open') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-3">No bed records were found for this room.</p>
                        <?php endif; ?>
                        <div class="d-grid gap-2 mt-3">
                            <a href="<?= url('views/student/beds/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-grid-3x3-gap me-1"></i>Open my bed page</a>
                            <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-people me-1"></i>Visitor requests</a>
                        </div>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
