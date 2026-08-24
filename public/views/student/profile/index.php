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

use App\Services\StudentService;

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

$pageTitle = 'Student Profile';
$pageStyles = ['student-profile.css'];
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student';
$initials = strtoupper(substr((string) ($student['firstName'] ?? 'S'), 0, 1) . substr((string) ($student['lastName'] ?? ''), 0, 1));
$status = strtolower((string) ($student['status'] ?? 'active'));
$statusClass = $status === 'active' ? 'success' : ($status === 'suspended' ? 'danger' : 'secondary');
$roomLabel = !empty($student['roomId']) ? (string) $student['roomId'] : 'Not assigned';
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
        <section class="profile-hero">
            <div class="profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
            <div class="profile-hero-copy">
                <div class="profile-kicker">Student account</div>
                <h1><?= e($studentName) ?></h1>
                <p><?= e($student['course'] ?? 'Course not specified') ?><?php if (!empty($student['level'])): ?> <span aria-hidden="true">&middot;</span> <?= e($student['level']) ?><?php endif; ?></p>
                <span class="badge text-bg-<?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span>
            </div>
            <a class="btn btn-light profile-edit-button" href="<?= url('views/student/profile/edit.php') ?>"><i class="bi bi-pencil me-1"></i>Edit profile</a>
        </section>

        <div class="row g-3 mt-1">
            <div class="col-md-4"><div class="profile-stat"><span class="profile-stat-icon blue"><i class="bi bi-credit-card-2-front"></i></span><div><small>Admission number</small><strong><?= e($student['admissionNo'] ?? 'Not specified') ?></strong></div></div></div>
            <div class="col-md-4"><div class="profile-stat"><span class="profile-stat-icon green"><i class="bi bi-book"></i></span><div><small>Academic level</small><strong><?= e($student['level'] ?? 'Not specified') ?></strong></div></div></div>
            <div class="col-md-4"><div class="profile-stat"><span class="profile-stat-icon orange"><i class="bi bi-door-open"></i></span><div><small>Room assignment</small><strong><?= e($roomLabel) ?></strong></div></div></div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <div class="profile-panel h-100">
                    <div class="profile-panel-heading"><div><span class="profile-kicker">Personal details</span><h2>Contact information</h2></div><i class="bi bi-person-lines-fill"></i></div>
                    <dl class="profile-details">
                        <div><dt>Full name</dt><dd><?= e($studentName) ?></dd></div>
                        <div><dt>Email address</dt><dd><?= e($student['email'] ?? 'Not specified') ?></dd></div>
                        <div><dt>Phone number</dt><dd><?= e($student['phone'] ?? 'Not specified') ?></dd></div>
                        <div><dt>Gender</dt><dd><?= e($student['gender'] ?? 'Not specified') ?></dd></div>
                    </dl>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="profile-panel h-100">
                    <div class="profile-panel-heading"><div><span class="profile-kicker">Emergency contact</span><h2>Guardian details</h2></div><i class="bi bi-shield-check"></i></div>
                    <dl class="profile-details">
                        <div><dt>Guardian name</dt><dd><?= e($student['guardianName'] ?? 'Not specified') ?></dd></div>
                        <div><dt>Guardian phone</dt><dd><?= e($student['guardianPhone'] ?? 'Not specified') ?></dd></div>
                    </dl>
                    <a class="profile-inline-link" href="<?= url('views/student/profile/edit.php') ?>">Update guardian details <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
