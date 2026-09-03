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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\FirebaseAuthService;

$user = current_user() ?? [];
$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_password') {
    $currentPassword = (string) ($_POST['currentPassword'] ?? '');
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if ($newPassword === '') {
        $errors['newPassword'] = 'New password is required.';
    } elseif (($passwordError = validate_password_policy($newPassword)) !== null) {
        $errors['newPassword'] = $passwordError;
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirmPassword'] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        try {
            // Update password in Firestore record or Firebase Auth
            FirebaseService::getInstance()->updateDocument('users', $userId, [
                'passwordUpdatedAt' => date(DATE_ATOM),
                'temp_password' => null,
            ]);

            // Attempt Firebase Auth password update if email is present
            if (!empty($user['email']) && !empty($currentPassword)) {
                try {
                    $signIn = FirebaseAuthService::signIn($user['email'], $currentPassword);
                    if (!empty($signIn['email'])) {
                        FirebaseAuthService::changePassword($signIn['email'], $currentPassword, $newPassword);
                    }
                } catch (\Throwable $authErr) {}
            }

            flash('success', 'Security credentials and password updated successfully.');
            redirect(url('views/senior-houseparent/profile/security/security.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update password: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Security & Password';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/senior-houseparent/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Account Security & Credentials</h5>
                <p class="text-muted mb-0">Manage login credentials and review active session security.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card stat-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-key me-2 text-primary"></i> Change Account Password</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_password">

                        <div class="mb-3">
                            <label class="form-label fw-bold" for="currentPassword">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="currentPassword" placeholder="Enter current password">
                            <small class="text-muted">Required if changing password via Firebase Auth.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold" for="newPassword">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?= !empty($errors['newPassword']) ? 'is-invalid' : '' ?>" id="newPassword" name="newPassword" placeholder="Minimum 6 characters" required>
                            <?php if (!empty($errors['newPassword'])): ?>
                                <div class="invalid-feedback"><?= e($errors['newPassword']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" for="confirmPassword">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?= !empty($errors['confirmPassword']) ? 'is-invalid' : '' ?>" id="confirmPassword" name="confirmPassword" placeholder="Re-type new password" required>
                            <?php if (!empty($errors['confirmPassword'])): ?>
                                <div class="invalid-feedback"><?= e($errors['confirmPassword']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/profile/index.php') ?>">Cancel</a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-shield-check me-1"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card stat-card p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-success"></i> Session & Device Audit</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">User UID</dt>
                        <dd class="col-sm-7"><code><?= e($userId ?: '—') ?></code></dd>

                        <dt class="col-sm-5 text-muted">Account Role</dt>
                        <dd class="col-sm-7"><span class="badge bg-primary-subtle text-primary border">Senior Houseparent</span></dd>

                        <dt class="col-sm-5 text-muted">Client IP</dt>
                        <dd class="col-sm-7"><?= e($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></dd>

                        <dt class="col-sm-5 text-muted">Browser</dt>
                        <dd class="col-sm-7 text-truncate"><?= e(mb_strimwidth($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser', 0, 30, '...')) ?></dd>

                        <dt class="col-sm-5 text-muted">Session Status</dt>
                        <dd class="col-sm-7"><span class="text-success fw-bold"><i class="bi bi-circle-fill fs-6 me-1"></i>Active</span></dd>
                    </dl>
                </div>

                <div class="card stat-card p-4 border-start border-4 border-warning">
                    <h6 class="fw-bold mb-2 text-warning"><i class="bi bi-info-circle me-1"></i> Security Guidelines</h6>
                    <ul class="text-muted small ps-3 mb-0">
                        <li>Never share your credentials with unauthorized individuals or students.</li>
                        <li>Always log out of your account when using shared terminal workstations.</li>
                        <li>Passwords should include a combination of letters, numbers, and symbols.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

