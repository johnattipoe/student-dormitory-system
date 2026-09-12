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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\UserService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors['current_password'] = 'Current password is required.';
    }
    $passwordError = validate_password_policy($newPassword);
    if ($passwordError !== null) {
        $errors['new_password'] = $passwordError;
    }
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        $result = $userService->updatePassword($userId, $newPassword, $currentPassword);
        if ($result['success']) {
            flash('success', 'Your password has been changed successfully.');
            redirect(url('views/nurse/profile/security/security.php'));
        } else {
            $errors['general'] = $result['message'] ?? 'Failed to update password.';
        }
    }
}

$pageTitle = 'Account Security';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/nurse/profile/index.php'), 'active' => true],
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
                <h5 class="mb-1">Clinical Staff Account Security</h5>
                <p class="text-muted mb-0">Update credentials and view active session details.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="row g-4" style="max-width: 900px;">
            <div class="col-md-7">
                <div class="card stat-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-key me-2 text-primary"></i> Change Password</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="current_password">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?= !empty($errors['current_password']) ? 'is-invalid' : '' ?>" id="current_password" name="current_password" required>
                            <?php if (!empty($errors['current_password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['current_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="new_password">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?= !empty($errors['new_password']) ? 'is-invalid' : '' ?>" id="new_password" name="new_password" required>
                            <?php if (!empty($errors['new_password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['new_password']) ?></div>
                            <?php endif; ?>
                            <div class="form-text small">Minimum 6 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required>
                            <?php if (!empty($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a class="btn btn-outline-secondary" href="<?= url('views/nurse/profile/index.php') ?>">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-shield-check me-1"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card stat-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i> Session Forensics</h6>
                    <div class="mb-3 pb-2 border-bottom">
                        <span class="text-muted small d-block">Nurse UID</span>
                        <span class="font-monospace small"><?= e($userId) ?></span>
                    </div>
                    <div class="mb-3 pb-2 border-bottom">
                        <span class="text-muted small d-block">IP Address</span>
                        <span class="font-monospace small"><?= e($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></span>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Session Status</span>
                        <span class="badge bg-success">Active & Verified</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

