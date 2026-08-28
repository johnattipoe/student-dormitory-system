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
$appConfig = app_config();

use App\Services\FirebaseAuthService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        flash('error', 'Reset token is missing or invalid.');
        redirect(base_url('views/auth/reset-password/reset-password.php'));
    }

    if (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters long.');
        redirect(base_url('views/auth/reset-password/reset-password.php?token=' . urlencode($token)));
    }

    if ($password !== $confirmPassword) {
        flash('error', 'Passwords do not match. Please try again.');
        redirect(base_url('views/auth/reset-password/reset-password.php?token=' . urlencode($token)));
    }

    $result = FirebaseAuthService::confirmPasswordReset($token, $password);

    if (!$result['success']) {
        flash('error', $result['message']);
        redirect(base_url('views/auth/reset-password/reset-password.php?token=' . urlencode($token)));
    }

    flash('success', 'Your password has been reset successfully. Please sign in.');
    redirect(base_url('login.php'));
}

$errorMessage = flash('error');
$successMessage = flash('success');
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login/login.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-lock fs-1 text-primary"></i>
                <h4 class="mt-2 mb-0">Reset Password</h4>
                <p class="text-muted small">Choose a new password</p>
                <?php if (!empty($appConfig['support_email'])): ?>
                    <p class="text-muted small mb-0">Need help? <a href="mailto:<?= e($appConfig['support_email']) ?>"><?= e($appConfig['support_email']) ?></a></p>
                <?php endif; ?>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger py-2"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success py-2"><?= e($successMessage) ?></div>
            <?php endif; ?>

            <form method="POST" action="/index.php?route=/views/auth/reset-password/reset-password.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-circle me-1"></i> Update Password
                </button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/login.php">Back to login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auth/auth.js"></script>
</body>
</html>
