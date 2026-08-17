<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;

$studentId = sanitize($_GET['id'] ?? '');
$student = $studentId !== '' ? StudentService::find($studentId) : null;

$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Student Medical Profile</h5>
            <?php if (!$student): ?>
                <div class="alert alert-warning mb-0">Student not found.</div>
            <?php else: ?>
            <div class="row g-3">
                <div class="col-md-6"><strong>Name:</strong> <?= e($student['firstName'] ?? '') ?> <?= e($student['lastName'] ?? '') ?></div>
                <div class="col-md-6"><strong>Email:</strong> <?= e($student['email'] ?? '') ?></div>
                <div class="col-md-6"><strong>Student ID:</strong> <?= e($student['studentId'] ?? $student['id'] ?? '') ?></div>
                <div class="col-md-6"><strong>Phone:</strong> <?= e($student['phone'] ?? '—') ?></div>
                <div class="col-md-6"><strong>Course:</strong> <?= e($student['course'] ?? '—') ?></div>
                <div class="col-md-6"><strong>Status:</strong> <?= e($student['status'] ?? 'active') ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
