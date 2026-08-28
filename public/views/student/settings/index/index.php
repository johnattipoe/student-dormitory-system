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

use App\Services\FirebaseService;
use App\Services\StudentService;

$pageTitle = 'Student Settings';
$pageStyles = ['student.css'];
$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$student = [];
$preferences = [
    'emailNotifications' => true,
    'attendanceAlerts' => true,
    'visitorUpdates' => true,
    'incidentAlerts' => true,
    'medicalAlerts' => true,
    'systemNotifications' => true,
    'notificationFrequency' => 'immediate',
    'quietHours' => '',
];
$settingsNotice = null;

if ($studentId) {
    try {
        $student = StudentService::find((string) $studentId) ?? [];
    } catch (Throwable $e) {
        $settingsNotice = 'Student profile details are temporarily unavailable.';
    }
}

if (empty($student) && !empty($currentUser['email'])) {
    try {
        foreach (StudentService::all() as $candidate) {
            if ((string) ($candidate['email'] ?? '') === (string) $currentUser['email']) {
                $student = $candidate;
                $studentId = $candidate['id'] ?? $studentId;
                break;
            }
        }
    } catch (Throwable $e) {
        $settingsNotice = 'Student profile details are temporarily unavailable.';
    }
}

try {
    $userId = $currentUser['uid'] ?? $currentUser['id'] ?? null;
    if ($userId) {
        $savedPrefs = FirebaseService::getInstance()->where('notification_preferences', 'userId', '=', $userId);
        if (!empty($savedPrefs[0]) && is_array($savedPrefs[0])) {
            $preferences = array_merge($preferences, $savedPrefs[0]);
        }
    }
} catch (Throwable $e) {
    $settingsNotice = $settingsNotice ?: 'Notification preferences are temporarily unavailable.';
}

$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') {
    $studentName = $currentUser['name'] ?? 'Student';
}

$studentInitials = strtoupper(substr((string) ($student['firstName'] ?? $studentName), 0, 1) . substr((string) ($student['lastName'] ?? ''), 0, 1));
$studentInitials = trim($studentInitials) !== '' ? $studentInitials : 'S';
$emailEnabled = !empty($preferences['emailNotifications']);
$activeAlerts = 0;
foreach (['attendanceAlerts', 'visitorUpdates', 'incidentAlerts', 'medicalAlerts', 'systemNotifications'] as $key) {
    if (!empty($preferences[$key])) {
        $activeAlerts++;
    }
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="student-settings-hero mb-4">
            <div class="student-settings-avatar" aria-hidden="true"><?= e($studentInitials) ?></div>
            <div class="student-settings-copy">
                <span class="student-settings-kicker">Student self-service</span>
                <h1>Settings for <?= e($studentName) ?></h1>
                <p>Manage your account, notifications, profile details, room information, visitors, and support shortcuts from one place.</p>
                <div class="student-settings-badges">
                    <span class="badge bg-primary"><i class="bi bi-person-check me-1"></i><?= e(ucfirst((string) ($currentUser['role'] ?? 'student'))) ?></span>
                    <span class="badge bg-info"><i class="bi bi-envelope me-1"></i><?= $emailEnabled ? 'Email alerts on' : 'Email alerts off' ?></span>
                    <span class="badge bg-success"><i class="bi bi-bell me-1"></i><?= e((string) $activeAlerts) ?>/5 alert types</span>
                </div>
            </div>
            <div class="student-settings-actions">
                <a href="<?= url('views/student/profile/edit/edit.php') ?>" class="btn btn-light"><i class="bi bi-pencil-square me-1"></i>Edit Profile</a>
                <a href="<?= url('views/student/settings/notification-preferences/notification-preferences.php') ?>" class="btn btn-primary"><i class="bi bi-sliders me-1"></i>Notifications</a>
            </div>
        </section>

        <?php if ($settingsNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($settingsNotice) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="student-settings-stat">
                    <span class="student-settings-stat-icon blue"><i class="bi bi-credit-card-2-front"></i></span>
                    <div><small>Admission number</small><strong><?= e($student['admissionNo'] ?? 'Not specified') ?></strong></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="student-settings-stat">
                    <span class="student-settings-stat-icon green"><i class="bi bi-door-open"></i></span>
                    <div><small>Room assignment</small><strong><?= e($student['roomId'] ?? 'Not assigned') ?></strong></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="student-settings-stat">
                    <span class="student-settings-stat-icon orange"><i class="bi bi-clock-history"></i></span>
                    <div><small>Notification frequency</small><strong><?= e(ucfirst((string) ($preferences['notificationFrequency'] ?? 'Immediate'))) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="row g-4">
                    <div class="col-md-6">
                        <article class="student-settings-card h-100">
                            <div class="student-settings-card-icon purple"><i class="bi bi-bell"></i></div>
                            <div>
                                <h2>Notification Preferences</h2>
                                <p>Choose alert types, quiet hours, email delivery, and how often you want updates.</p>
                            </div>
                            <ul class="student-settings-list">
                                <li><span>Email notifications</span><strong class="<?= $emailEnabled ? 'text-success' : 'text-danger' ?>"><?= $emailEnabled ? 'Enabled' : 'Disabled' ?></strong></li>
                                <li><span>Quiet hours</span><strong><?= e($preferences['quietHours'] ?: 'Not set') ?></strong></li>
                                <li><span>Active alert types</span><strong><?= e((string) $activeAlerts) ?>/5</strong></li>
                            </ul>
                            <a href="<?= url('views/student/settings/notification-preferences/notification-preferences.php') ?>" class="btn btn-outline-primary mt-auto"><i class="bi bi-sliders me-1"></i>Manage notifications</a>
                        </article>
                    </div>

                    <div class="col-md-6">
                        <article class="student-settings-card h-100">
                            <div class="student-settings-card-icon blue"><i class="bi bi-person-lines-fill"></i></div>
                            <div>
                                <h2>Profile & Contact</h2>
                                <p>Review and update your phone number and emergency guardian information.</p>
                            </div>
                            <ul class="student-settings-list">
                                <li><span>Email</span><strong><?= e($student['email'] ?? $currentUser['email'] ?? 'Not specified') ?></strong></li>
                                <li><span>Phone</span><strong><?= e($student['phone'] ?? 'Not specified') ?></strong></li>
                                <li><span>Guardian</span><strong><?= e($student['guardianName'] ?? 'Not specified') ?></strong></li>
                            </ul>
                            <a href="<?= url('views/student/profile/edit/edit.php') ?>" class="btn btn-outline-primary mt-auto"><i class="bi bi-pencil me-1"></i>Edit contact details</a>
                        </article>
                    </div>

                    <div class="col-md-6">
                        <article class="student-settings-card h-100">
                            <div class="student-settings-card-icon green"><i class="bi bi-house-door"></i></div>
                            <div>
                                <h2>Room & Residence</h2>
                                <p>View your assigned room, roommate information, and current residence details.</p>
                            </div>
                            <ul class="student-settings-list">
                                <li><span>Room</span><strong><?= e($student['roomId'] ?? 'Not assigned') ?></strong></li>
                                <li><span>House</span><strong><?= e($student['houseId'] ?? 'Not specified') ?></strong></li>
                                <li><span>Status</span><strong><?= e(ucfirst((string) ($student['status'] ?? 'Active'))) ?></strong></li>
                            </ul>
                            <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-success mt-auto"><i class="bi bi-door-open me-1"></i>View room</a>
                        </article>
                    </div>

                    <div class="col-md-6">
                        <article class="student-settings-card h-100">
                            <div class="student-settings-card-icon orange"><i class="bi bi-people"></i></div>
                            <div>
                                <h2>Visitors & Requests</h2>
                                <p>Request visitors, review approvals, and track visitor activity linked to your account.</p>
                            </div>
                            <div class="student-settings-button-grid">
                                <a href="<?= url('views/student/visitors/index/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-list-check me-1"></i>Visitor log</a>
                                <a href="<?= url('views/visitors/request/request.php') ?>" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>New request</a>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="student-settings-side-panel">
                    <div class="student-settings-side-header">
                        <span class="student-settings-kicker">Account security</span>
                        <h2>Login & access</h2>
                    </div>
                    <div class="student-settings-security-row">
                        <i class="bi bi-shield-check text-success"></i>
                        <div>
                            <strong>Protected student portal</strong>
                            <span>Your session is role-restricted to student pages.</span>
                        </div>
                    </div>
                    <div class="student-settings-security-row">
                        <i class="bi bi-person-badge text-primary"></i>
                        <div>
                            <strong>Account ID</strong>
                            <span><?= e($currentUser['uid'] ?? $currentUser['id'] ?? 'Not available') ?></span>
                        </div>
                    </div>
                    <div class="student-settings-security-row">
                        <i class="bi bi-envelope-check text-info"></i>
                        <div>
                            <strong>Email address</strong>
                            <span><?= e($currentUser['email'] ?? $student['email'] ?? 'Not available') ?></span>
                        </div>
                    </div>
                    <hr>
                    <h3>Quick actions</h3>
                    <div class="d-grid gap-2">
                        <a href="<?= url('views/student/notifications/index/index.php') ?>" class="btn btn-outline-primary"><i class="bi bi-bell me-1"></i>Open notifications</a>
                        <a href="<?= url('views/student/attendance/index/index.php') ?>" class="btn btn-outline-success"><i class="bi bi-calendar-check me-1"></i>Attendance records</a>
                        <a href="<?= url('views/student/incidents/index/index.php') ?>" class="btn btn-outline-danger"><i class="bi bi-flag me-1"></i>Incident records</a>
                        <a href="<?= url('logout.php') ?>" class="btn btn-secondary"><i class="bi bi-box-arrow-right me-1"></i>Logout safely</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
