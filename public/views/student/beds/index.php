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

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$student = null;
$bed = null;
$room = null;
$house = null;
$roomBeds = [];
$pageNotice = null;
$allBeds = [];

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

    $allBeds = BedService::all();

    if ($student) {
        $studentDocumentId = (string) ($student['id'] ?? $studentId ?? '');
        foreach ($allBeds as $candidate) {
            if ((string) ($candidate['studentId'] ?? '') === $studentDocumentId) {
                $bed = $candidate;
                break;
            }
        }

        $roomId = (string) ($student['roomId'] ?? $bed['roomId'] ?? '');
        if ($roomId !== '') {
            $room = RoomService::find($roomId);
            foreach ($allBeds as $candidate) {
                if ((string) ($candidate['roomId'] ?? '') === $roomId) {
                    $roomBeds[] = $candidate;
                }
            }
        }
    }

    $houseId = (string) ($student['houseId'] ?? $room['houseId'] ?? '');
    if ($houseId !== '') {
        $house = HouseService::find($houseId);
    }
} catch (Throwable $e) {
    $pageNotice = 'Bed information is temporarily unavailable. Please try again later.';
}

$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') {
    $studentName = $currentUser['name'] ?? 'Student';
}

$bedStatus = strtolower((string) ($bed['status'] ?? 'unassigned'));
$roomCapacity = (int) ($room['capacity'] ?? count($roomBeds) ?: 0);
$roomOccupied = (int) ($room['occupied'] ?? count(array_filter($roomBeds, static fn($item) => !empty($item['studentId']))));
$roomAvailable = max(0, $roomCapacity - $roomOccupied);
$occupancyRate = $roomCapacity > 0 ? min(100, round(($roomOccupied / $roomCapacity) * 100)) : 0;
$roomStatus = ($room['status'] ?? '') === 'maintenance'
    ? 'maintenance'
    : ($roomCapacity > 0 && $roomOccupied >= $roomCapacity ? 'full' : ($roomOccupied > 0 ? 'occupied' : 'available'));
$statusClass = match ($bedStatus) {
    'occupied' => 'success',
    'maintenance' => 'secondary',
    'available' => 'warning',
    default => 'info',
};
$bedNumber = (string) ($bed['bedNumber'] ?? 'Not assigned');
$roomNumber = (string) ($room['roomNumber'] ?? 'Not assigned');
$houseName = (string) ($house['name'] ?? $house['houseName'] ?? 'Not assigned');
$block = (string) ($room['block'] ?? $room['blockName'] ?? 'Not specified');

$pageTitle = 'My Bed';
$pageStyles = ['student.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/student/beds/index.php'), 'active' => true],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="student-bed-hero mb-4">
            <div class="student-bed-hero-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            <div>
                <span class="student-settings-kicker">Accommodation</span>
                <h1>My Bed Assignment</h1>
                <p>View your assigned bed, room occupancy, house details, and accommodation status.</p>
            </div>
            <div class="student-bed-hero-actions">
                <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-light"><i class="bi bi-house-door me-1"></i>Room details</a>
                <a href="<?= url('views/student/profile/index.php') ?>" class="btn btn-primary"><i class="bi bi-person-circle me-1"></i>Profile</a>
            </div>
        </section>

        <?php if ($pageNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($pageNotice) ?></div>
        <?php endif; ?>

        <?php if (!$student || !$bed): ?>
            <div class="student-bed-empty">
                <div class="student-bed-empty-icon"><i class="bi bi-bed"></i></div>
                <h2>No bed assigned yet</h2>
                <p>Your house parent or administrator has not assigned a bed to your account yet. You can still review your room page or contact support.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-house-door me-1"></i>Check room information</a>
                    <a href="<?= url('views/student/settings/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Open settings</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="student-bed-stat"><span class="student-bed-stat-icon blue"><i class="bi bi-bed"></i></span><div><small>Assigned bed</small><strong><?= e($bedNumber) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-bed-stat"><span class="student-bed-stat-icon green"><i class="bi bi-door-open"></i></span><div><small>Room</small><strong><?= e($roomNumber) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-bed-stat"><span class="student-bed-stat-icon orange"><i class="bi bi-people"></i></span><div><small>Occupancy</small><strong><?= e((string) $roomOccupied) ?> / <?= e((string) $roomCapacity) ?></strong></div></div></div>
                <div class="col-md-3"><div class="student-bed-stat"><span class="student-bed-stat-icon purple"><i class="bi bi-check2-circle"></i></span><div><small>Spaces left</small><strong><?= e((string) $roomAvailable) ?></strong></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="student-bed-main-card mb-4">
                        <div class="student-bed-card-header">
                            <div>
                                <span class="student-settings-kicker">Current assignment</span>
                                <h2><?= e($studentName) ?></h2>
                                <p>Admission number: <?= e($student['admissionNo'] ?? 'Not specified') ?></p>
                            </div>
                            <span class="badge bg-<?= e($statusClass) ?>"><?= e(ucfirst($bedStatus)) ?></span>
                        </div>

                        <div class="student-bed-layout">
                            <div class="student-bed-visual">
                                <i class="bi bi-bed"></i>
                                <strong><?= e($bedNumber) ?></strong>
                                <span>Bed number</span>
                            </div>
                            <dl class="student-bed-details">
                                <div><dt>Bed capacity</dt><dd><?= e((string) ($bed['capacity'] ?? 1)) ?></dd></div>
                                <div><dt>Room</dt><dd><?= e($roomNumber) ?></dd></div>
                                <div><dt>Block</dt><dd><?= e($block) ?></dd></div>
                                <div><dt>Room status</dt><dd><?= e(ucfirst($roomStatus)) ?></dd></div>
                                <div><dt>House</dt><dd><?= e($houseName) ?></dd></div>
                                <div><dt>House gender</dt><dd><?= e($house['gender'] ?? 'Not specified') ?></dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="student-bed-main-card">
                        <div class="student-bed-card-header">
                            <div>
                                <span class="student-settings-kicker">Room occupancy</span>
                                <h2>Room <?= e($roomNumber) ?> bed map</h2>
                                <p><?= e((string) $roomOccupied) ?> of <?= e((string) $roomCapacity) ?> spaces currently occupied.</p>
                            </div>
                            <strong class="student-bed-rate"><?= e((string) $occupancyRate) ?>%</strong>
                        </div>
                        <div class="progress student-bed-progress mb-3" role="progressbar" aria-valuenow="<?= e((string) $occupancyRate) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= e((string) $occupancyRate) ?>%"></div>
                        </div>

                        <?php if ($roomBeds): ?>
                            <div class="student-bed-grid">
                                <?php foreach ($roomBeds as $roomBed): ?>
                                    <?php
                                    $isMine = (string) ($roomBed['id'] ?? '') === (string) ($bed['id'] ?? '');
                                    $itemStatus = strtolower((string) ($roomBed['status'] ?? 'available'));
                                    $occupiedBy = !empty($roomBed['studentId']);
                                    ?>
                                    <div class="student-bed-item <?= $isMine ? 'is-mine' : '' ?> <?= $occupiedBy ? 'is-occupied' : 'is-open' ?>">
                                        <i class="bi bi-bed"></i>
                                        <strong><?= e($roomBed['bedNumber'] ?? '-') ?></strong>
                                        <span><?= $isMine ? 'Your bed' : e(ucfirst($itemStatus)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No additional bed records were found for this room.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-xl-4">
                    <aside class="student-bed-side-card">
                        <span class="student-settings-kicker">Bed status</span>
                        <h2>Accommodation checklist</h2>
                        <div class="student-bed-checklist">
                            <div><i class="bi bi-check-circle-fill text-success"></i><span>Student profile linked</span></div>
                            <div><i class="bi bi-check-circle-fill text-success"></i><span>Room assignment found</span></div>
                            <div><i class="bi bi-check-circle-fill text-success"></i><span>Bed assignment active</span></div>
                            <div><i class="bi bi-info-circle-fill text-info"></i><span>Report any room issue to your house parent</span></div>
                        </div>
                        <hr>
                        <h3>Quick actions</h3>
                        <div class="d-grid gap-2">
                            <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-house-door me-1"></i>View room page</a>
                            <a href="<?= url('views/student/incidents/create.php') ?>" class="btn btn-outline-danger"><i class="bi bi-flag me-1"></i>Report issue</a>
                            <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-people me-1"></i>Visitor requests</a>
                        </div>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
