<?php
require __DIR__ . '/../../bootstrap.php';
$appConfig = app_config();

use App\Services\FirebaseAuthService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $verificationCode = trim((string) ($_POST['verification_code'] ?? ''));

    if (!empty($email) && !empty($verificationCode)) {
        $result = FirebaseAuthService::sendEmailVerification($email);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('views/auth/verify-account.php'));
        }

        flash('success', 'A verification email has been sent to ' . $email . '. Follow the link to complete verification.');
        redirect(base_url('views/auth/verify-account.php'));
    }

    if (empty($verificationCode) || strlen($verificationCode) < 6) {
        flash('error', 'Please enter a valid verification code.');
        redirect(base_url('views/auth/verify-account.php'));
    }

    flash('success', 'Your account has been verified successfully.');
    redirect(base_url('login.php'));
}

$errorMessage = flash('error');
$successMessage = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account | <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-person-check fs-1 text-primary"></i>
                <h4 class="mt-2 mb-0">Verify Account</h4>
                <p class="text-muted small">Enter the code sent to your email</p>
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

            <form method="POST" action="/index.php?route=/views/auth/verify-account.php">
                <div class="mb-3">
                    <label class="form-label">Verification Code</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-keyboard"></i></span>
                        <input type="text" name="verification_code" class="form-control" placeholder="Enter 6-digit code" maxlength="12" autocomplete="one-time-code" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-shield-check me-1"></i> Verify My Account
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
