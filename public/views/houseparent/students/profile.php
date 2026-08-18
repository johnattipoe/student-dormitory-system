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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;

$studentId = sanitize($_GET['studentId'] ?? '');
$student = $studentId ? StudentService::find($studentId) : null;
$students = StudentService::all(current_user()['houseId'] ?? null);
$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
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
                <a href="<?= url('views/houseparent/students/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to students</a>
            </div>

            <?php if (!$student): ?>
                <p class="text-muted">Select a student from the list to view their profile.</p>
                <div class="list-group">
                    <?php foreach ($students as $item): ?>
                        <a href="<?= url('views/houseparent/students/profile.php?studentId=' . urlencode((string) ($item['id'] ?? ''))) ?>" class="list-group-item list-group-item-action">
                            <?= e(trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? ''))) ?> (<?= e($item['admissionNo'] ?? '—') ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Basic Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <span><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></span></p>
                            <p class="mb-1"><strong>Admission No.:</strong> <span><?= e($student['admissionNo'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Course:</strong> <span><?= e($student['course'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Level:</strong> <span><?= e($student['level'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>House:</strong> <span><?= e($student['houseId'] ?? '—') ?></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Contact</h6>
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
