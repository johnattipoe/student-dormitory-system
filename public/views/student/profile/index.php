<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php'), 'active' => true],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">My Profile</h5>
            <div class="row g-3">
                <div class="col-md-6"><strong>Name:</strong> <?= e($student['firstName'] ?? '') ?> <?= e($student['lastName'] ?? '') ?></div>
                <div class="col-md-6"><strong>Email:</strong> <?= e($student['email'] ?? '') ?></div>
                <div class="col-md-6"><strong>Phone:</strong> <?= e($student['phone'] ?? '—') ?></div>
                <div class="col-md-6"><strong>Course:</strong> <?= e($student['course'] ?? '—') ?></div>
                <div class="col-md-6"><strong>Admission No.:</strong> <?= e($student['admissionNo'] ?? '') ?></div>
                <div class="col-md-6"><strong>Status:</strong> <?= e($student['status'] ?? '—') ?></div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
