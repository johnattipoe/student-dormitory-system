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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseAuthService;
use App\Services\MedicalService;
use App\Services\StudentService;
use App\Services\UserService;

$user = current_user();
$userId = current_user_id();
$userProfile = $userId ? (new UserService())->find($userId) : null;
if (is_array($userProfile)) {
    $user = array_merge($user, $userProfile);
}
$medicalService = new MedicalService();
$studentCount = StudentService::count();
$recordCount = $medicalService->count();
$emergencyCount = $medicalService->emergencyCases();
$csrfToken = $_SESSION['nurse_profile_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['nurse_profile_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/nurse/profile.php'));
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update_profile') {
        $name = trim((string) sanitize($_POST['name'] ?? ''));
        $phone = trim((string) sanitize($_POST['phone'] ?? ''));
        if ($name === '') {
            flash('error', 'Your name is required.');
        } elseif (!$userId) {
            flash('error', 'Unable to identify your account.');
        } else {
            $result = (new UserService())->update($userId, ['name' => $name, 'phone' => $phone]);
            if ($result['success']) {
                session_put(AUTH_USER_SESSION, array_merge($user, ['name' => $name, 'phone' => $phone]));
            }
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
        redirect(url('views/nurse/profile.php'));
    }

    if ($action === 'send_password_reset') {
        $email = trim((string) ($user['email'] ?? ''));
        try {
            $result = $email !== ''
                ? FirebaseAuthService::sendPasswordResetEmail($email)
                : ['success' => false, 'message' => 'No email address is associated with your account.'];
            flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Password reset instructions were sent to your email.' : $result['message']);
        } catch (Throwable $exception) {
            flash('error', 'Unable to send password reset instructions.');
        }
        redirect(url('views/nurse/profile.php'));
    }
}

$userName = trim((string) ($user['name'] ?? 'Nurse')) ?: 'Nurse';
$userRole = str_replace('_', ' ', (string) ($user['role'] ?? 'nurse'));
$nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$userInitials = strtoupper(substr($nameParts[0] ?? 'N', 0, 1) . (count($nameParts) > 1 ? substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
$pageTitle = 'My Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/nurse/profile.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper nurse-portal">
        <section class="nurse-profile-hero">
            <div class="nurse-profile-avatar" aria-hidden="true"><?= e($userInitials) ?></div>
            <div class="nurse-profile-heading">
                <span class="nurse-kicker">Nurse account</span>
                <h1><?= e($userName) ?></h1>
                <p>Clinical care workspace</p>
                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Active account</span>
            </div>
            <div class="nurse-profile-mark"><i class="bi bi-heart-pulse"></i></div>
        </section>

        <div class="row g-4 mt-1">
            <div class="col-lg-8">
                <section class="nurse-card-panel h-100">
                    <div class="nurse-card-header">
                        <div>
                            <span class="nurse-kicker">Identity</span>
                            <h2>Personal details</h2>
                            <p>Information associated with your staff account.</p>
                        </div>
                        <i class="bi bi-person-vcard nurse-profile-section-icon"></i>
                    </div>
                    <form method="POST" class="nurse-profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="profile-name">Full name</label>
                                <input id="profile-name" class="form-control" name="name" value="<?= e($userName) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile-email">Email address</label>
                                <input id="profile-email" class="form-control" value="<?= e($user['email'] ?? 'Not specified') ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile-phone">Phone number</label>
                                <input id="profile-phone" class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile-role">Staff role</label>
                                <input id="profile-role" class="form-control text-capitalize" value="<?= e($userRole) ?>" disabled>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-4" type="submit"><i class="bi bi-check2 me-1"></i>Save profile</button>
                    </form>
                </section>
            </div>
            <div class="col-lg-4">
                <aside class="nurse-side-card h-100">
                    <span class="nurse-kicker">Access</span>
                    <h2>Portal access</h2>
                    <div class="nurse-profile-access">
                        <div><i class="bi bi-heart-pulse-fill"></i><span>Clinical workspace</span><strong>Enabled</strong></div>
                        <div><i class="bi bi-person-badge"></i><span>Account ID</span><strong><?= e($user['uid'] ?? $user['id'] ?? 'Assigned') ?></strong></div>
                        <div><i class="bi bi-clock-history"></i><span>Session status</span><strong>Active</strong></div>
                    </div>
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="send_password_reset">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-key me-1"></i>Reset password</button>
                    </form>
                </aside>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4"><div class="nurse-stat"><span class="nurse-stat-icon green"><i class="bi bi-people"></i></span><div><small>Students supported</small><strong><?= e((string) $studentCount) ?></strong></div></div></div>
            <div class="col-md-4"><div class="nurse-stat"><span class="nurse-stat-icon blue"><i class="bi bi-journal-medical"></i></span><div><small>Medical records</small><strong><?= e((string) $recordCount) ?></strong></div></div></div>
            <div class="col-md-4"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-exclamation-octagon"></i></span><div><small>Emergency cases</small><strong><?= e((string) $emergencyCount) ?></strong></div></div></div>
        </div>

        <section class="nurse-profile-actions mt-4">
            <div>
                <span class="nurse-kicker">Workspace</span>
                <h2>Return to care tools</h2>
            </div>
            <div class="nurse-hero-actions">
                <a class="btn btn-outline-primary" href="<?= url('views/nurse/students/students.php') ?>"><i class="bi bi-people me-1"></i>Student directory</a>
                <a class="btn btn-primary" href="<?= url('views/nurse/medical-records/medical-records.php') ?>"><i class="bi bi-journal-medical me-1"></i>Medical records</a>
            </div>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
