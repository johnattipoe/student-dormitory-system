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

use App\Services\StudentService;
use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;
$studentId = sanitize($_GET['studentId'] ?? $_GET['id'] ?? '');
$students = StudentService::all($houseId);
$student = $studentId ? StudentService::find($studentId) : null;
$room = ($student && !empty($student['roomId'])) ? RoomService::find((string) $student['roomId']) : null;
$roomName = $room['roomNumber'] ?? ($student['roomId'] ?? '—');

if ($student && ($student['houseId'] ?? null) !== $houseId) {
    $student = null;
}

$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : 'Student';
$initials = strtoupper(substr((string) ($student['firstName'] ?? 'S'), 0, 1) . substr((string) ($student['lastName'] ?? ''), 0, 1));
$status = strtolower((string) ($student['status'] ?? 'active'));
$statusClass = $status === 'active' ? 'success' : ($status === 'suspended' ? 'danger' : 'secondary');

$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
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
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <?php if (!$student): ?>
            <!-- Hero Header for Directory Selection -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-mortarboard-fill text-primary me-2"></i>Resident Student Profile</h4>
                    <p class="text-muted mb-0">Select a student from your house to view complete records</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= url('views/house-master/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Directory
                    </a>
                </div>
            </div>

            <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>House Resident Students (<?= count($students) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($students as $item): ?>
                            <a href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode((string) ($item['id'] ?? ''))) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <strong class="text-dark d-block"><?= e(trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? ''))) ?></strong>
                                    <small class="text-muted font-monospace"><?= e($item['admissionNo'] ?? 'No ID') ?> &bull; Room <?= e($item['roomNumber'] ?? $item['roomId'] ?? '—') ?></small>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Hero Header for Student Profile -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width: 72px; height: 72px;">
                        <?= e($initials ?: 'S') ?>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark"><?= e($studentName) ?></h4>
                        <p class="text-muted mb-0">
                            Admission No: <strong class="font-monospace text-primary"><?= e($student['admissionNo'] ?? '—') ?></strong>
                            &bull; Room <strong><?= e($roomName) ?></strong>
                            &bull; <span class="badge bg-<?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span>
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/students/edit/edit.php?studentId=' . urlencode($studentId)) ?>">
                        <i class="bi bi-pencil me-1"></i>Edit Student
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/index/index.php') ?>">
                        <i class="bi bi-arrow-left me-1"></i>All Students
                    </a>
                </div>
            </div>

            <!-- Quick Stats / Action Links -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Form / Level</span>
                                <h3 class="fw-bold my-1 text-primary"><?= e($student['form'] ?? $student['level'] ?? '—') ?></h3>
                                <span class="small text-muted"><?= e($student['course'] ?? 'General Course') ?></span>
                            </div>
                            <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-mortarboard fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Attendance Log</span>
                                <h3 class="fw-bold my-1 text-success">Roll Call</h3>
                                <a href="<?= url('views/house-master/attendance/history/history.php?studentId=' . urlencode($studentId)) ?>" class="small text-success text-decoration-none">
                                    View attendance history <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-calendar-check fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Visitor Logs</span>
                                <h3 class="fw-bold my-1 text-info">Guests</h3>
                                <a href="<?= url('views/house-master/visitors/index/index.php?search=' . urlencode($studentName)) ?>" class="small text-info text-decoration-none">
                                    View visitor records <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-people fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details Section -->
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card stat-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Academic & Dormitory Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Full Name</span>
                                    <strong><?= e($studentName) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Admission Number</span>
                                    <strong class="font-monospace"><?= e($student['admissionNo'] ?? '—') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Gender</span>
                                    <strong><?= e(ucfirst((string)($student['gender'] ?? 'Not specified'))) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Form / Level</span>
                                    <strong><?= e($student['form'] ?? $student['level'] ?? '—') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Class / Section</span>
                                    <strong><?= e($student['class'] ?? '—') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Course / Program</span>
                                    <strong><?= e($student['course'] ?? '—') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">NHIS Health Card No.</span>
                                    <strong><?= e($student['nhisNumber'] ?? 'Not registered') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Dormitory Room</span>
                                    <strong>Room <?= e($roomName) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card stat-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Contact & Guardian Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <span class="text-muted small d-block">Student Direct Contact</span>
                                <strong><?= e($student['phone'] ?? 'Not provided') ?></strong>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small d-block">Guardian Name</span>
                                <strong class="fs-6"><?= e($student['guardianName'] ?? '—') ?></strong>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small d-block">Guardian Phone Number</span>
                                <strong class="text-primary"><?= e($student['guardianPhone'] ?? '—') ?></strong>
                            </div>
                            <div class="mb-4">
                                <span class="text-muted small d-block">Guardian Email Address</span>
                                <strong><?= e($student['guardianEmail'] ?? '—') ?></strong>
                            </div>
                            <div class="pt-3 border-top">
                                <a class="btn btn-outline-primary btn-sm w-100" href="<?= url('views/house-master/students/edit/edit.php?studentId=' . urlencode($studentId)) ?>">
                                    <i class="bi bi-pencil me-1"></i>Update Information
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
