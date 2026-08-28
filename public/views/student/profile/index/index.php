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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\HouseService;
use App\Services\BedService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$student = [];

if ($studentId) {
    $student = StudentService::find((string) $studentId) ?? [];
}

if (empty($student) && !empty(current_user()['email'])) {
    foreach (StudentService::all() as $candidate) {
        if ((string) ($candidate['email'] ?? '') === (string) current_user()['email']) {
            $student = $candidate;
            break;
        }
    }
}

$room = !empty($student['roomId']) ? RoomService::find((string) $student['roomId']) : null;
$roomLabel = $room['roomNumber'] ?? ($student['roomId'] ?? 'Not assigned');

$house = !empty($student['houseId']) ? HouseService::find((string) $student['houseId']) : null;
$houseLabel = $house['name'] ?? 'Not assigned';

$bed = null;
if (!empty($student['id'])) {
    foreach (BedService::all() as $candidateBed) {
        if ((string) ($candidateBed['studentId'] ?? '') === (string) $student['id']) {
            $bed = $candidateBed;
            break;
        }
    }
}
$bedLabel = $bed['bedNumber'] ?? 'Not assigned';

$pageTitle = 'Student Profile';
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student';
$initials = strtoupper(substr((string) ($student['firstName'] ?? 'S'), 0, 1) . substr((string) ($student['lastName'] ?? ''), 0, 1));
$status = strtolower((string) ($student['status'] ?? 'active'));
$statusClass = $status === 'active' ? 'success' : ($status === 'suspended' ? 'danger' : 'secondary');

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php'), 'active' => true],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header with Profile Avatar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width: 72px; height: 72px;">
                    <?= e($initials ?: 'S') ?>
                </div>
                <div>
                    <h4 class="mb-1 fw-bold text-dark"><?= e($studentName) ?></h4>
                    <p class="text-muted mb-0">
                        <?= e($student['course'] ?? 'Course not specified') ?>
                        <?php if (!empty($student['form'] ?? $student['level'])): ?>
                            &bull; Form <?= e($student['form'] ?? $student['level']) ?>
                        <?php endif; ?>
                        &bull; <span class="badge bg-<?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span>
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/student/profile/edit/edit.php') ?>">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/profile/security/security.php') ?>">
                    <i class="bi bi-shield-lock me-1"></i>Security
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Admission Number</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e($student['admissionNo'] ?? 'Not set') ?></h3>
                            <span class="small text-muted">Student identifier</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-credit-card-2-front fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">House & Room</span>
                            <h3 class="fw-bold my-1 text-info"><?= e($houseLabel) ?></h3>
                            <span class="small text-muted">Room <?= e($roomLabel) ?> &bull; Bed <?= e($bedLabel) ?></span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Class / Program</span>
                            <h3 class="fw-bold my-1 text-success"><?= e($student['class'] ?? ($student['course'] ?? 'General')) ?></h3>
                            <span class="small text-muted">Level <?= e($student['level'] ?? '—') ?></span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-book fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <!-- Academic & Personal Details -->
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Academic & Personal Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Full Name</span>
                                <strong><?= e($studentName) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Gender</span>
                                <strong><?= e(ucfirst((string)($student['gender'] ?? 'Not specified'))) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Academic Course</span>
                                <strong><?= e($student['course'] ?? 'Not specified') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Form / Level</span>
                                <strong><?= e($student['form'] ?? $student['level'] ?? 'Not specified') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">NHIS Health Insurance No.</span>
                                <strong><?= e($student['nhisNumber'] ?? 'Not registered') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Phone Number</span>
                                <strong><?= e($student['phone'] ?? 'Not provided') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">House Assignment</span>
                                <strong><?= e($houseLabel) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Room & Bed</span>
                                <strong>Room <?= e($roomLabel) ?>, Bed <?= e($bedLabel) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Guardian & Emergency Contacts -->
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Guardian & Emergency Contact</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <span class="text-muted small d-block">Guardian Full Name</span>
                            <strong class="fs-6"><?= e($student['guardianName'] ?? 'Not specified') ?></strong>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted small d-block">Guardian Phone Number</span>
                            <strong class="text-primary"><?= e($student['guardianPhone'] ?? 'Not provided') ?></strong>
                        </div>
                        <div class="mb-4">
                            <span class="text-muted small d-block">Guardian Email Address</span>
                            <strong><?= e($student['guardianEmail'] ?? 'Not provided') ?></strong>
                        </div>
                        <div class="pt-3 border-top">
                            <a class="btn btn-outline-primary btn-sm w-100" href="<?= url('views/student/profile/edit/edit.php') ?>">
                                <i class="bi bi-pencil me-1"></i>Update Contact Information
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
