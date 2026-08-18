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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$studentId = sanitize($_GET['studentId'] ?? '');
$students = StudentService::all($houseId);
$student = $studentId ? StudentService::find($studentId) : null;

if ($student && ($student['houseId'] ?? null) !== $houseId) {
    $student = null;
}

$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Student Profile</h5>
                <a href="<?= url('views/house-master/students/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to students</a>
            </div>

            <?php if (!$student): ?>
                <p class="text-muted">Select a student from the list to view their profile.</p>
                <div class="list-group">
                    <?php foreach ($students as $item): ?>
                        <a href="<?= url('views/house-master/students/profile.php?studentId=' . urlencode((string) ($item['id'] ?? ''))) ?>" class="list-group-item list-group-item-action">
                            <?= e(trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? ''))) ?> (<?= e($item['admissionNo'] ?? '—') ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Basic Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <span><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></span></p>
                            <p class="mb-1"><strong>Admission No.:</strong> <span><?= e($student['admissionNo'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Course:</strong> <span><?= e($student['course'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Level:</strong> <span><?= e($student['level'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Room:</strong> <span><?= e($student['roomId'] ?? '—') ?></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Contact & Status</h6>
                            <p class="mb-1"><strong>Email:</strong> <span><?= e($student['email'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Phone:</strong> <span><?= e($student['phone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian:</strong> <span><?= e($student['guardianName'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian Phone:</strong> <span><?= e($student['guardianPhone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span><?= e($student['status'] ?? 'active') ?></span></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
