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
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email) || !validate_email($email)) {
        flash('error', 'Please enter a valid email address.');
        redirect(base_url('views/auth/forgot-password.php'));
    }

    $result = FirebaseAuthService::sendPasswordResetEmail($email);

    if (!$result['success']) {
        flash('error', $result['message']);
        redirect(base_url('views/auth/forgot-password.php'));
    }

    flash('success', 'Password reset instructions were sent to ' . $email . '.');
    redirect(base_url('views/auth/forgot-password.php'));
}

$errorMessage = flash('error');
$successMessage = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock fs-1 text-primary"></i>
                <h4 class="mt-2 mb-0">Forgot Password</h4>
                <p class="text-muted small">Enter your email to reset your password</p>
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

            <form method="POST" action="/index.php?route=/views/auth/forgot-password.php">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" autocomplete="email" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-send me-1"></i> Send Reset Link
                </button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/login.php">Back to login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auth.js"></script>
</body>
</html>
