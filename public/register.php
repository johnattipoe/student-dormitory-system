<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$appConfig = app_config();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/functions.php';

use App\Services\UserService;

$appConfig = require __DIR__ . '/../app/config/app.php';
if (empty($appConfig['allow_self_registration'])) {
    flash('error', 'Self-registration is disabled by the administrator.');
    redirect(base_url('login.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'student');

    $errors = validate_required([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $confirmPassword,
    ], ['name', 'email', 'password', 'confirm_password']);

    if (!empty($errors) || !validate_email($email)) {
        flash('error', 'Please complete all fields with a valid email address.');
        redirect(base_url('register.php'));
    }

    if ($password !== $confirmPassword) {
        flash('error', 'Passwords do not match. Please try again.');
        redirect(base_url('register.php'));
    }

    $userService = new UserService();
    $result = $userService->create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => $role,
        'emailVerified' => empty($appConfig['require_email_verification']) ? true : false,
    ]);

    if (!$result['success']) {
        flash('error', $result['message']);
        redirect(base_url('register.php'));
    }

    if (!empty($appConfig['require_email_verification'])) {
        flash('success', 'Registration successful. Please check your email to verify your account.');
    } else {
        flash('success', 'Registration successful. Please sign in to continue.');
    }
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
    <title>Create Account | <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-person-plus fs-1 text-primary"></i>
                <h4 class="mt-2 mb-0">Create Account</h4>
                <p class="text-muted small">Register a new dormitory account</p>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger py-2"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success py-2"><?= e($successMessage) ?></div>
            <?php endif; ?>

            <form method="POST" action="/register.php">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="name" class="form-control" autocomplete="name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" autocomplete="email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="student">Student</option>
                        <option value="houseparent">House Parent</option>
                        <option value="house_master">House Master</option>
                        <option value="house_mistress">House Mistress</option>
                        <option value="security">Security</option>
                        <option value="nurse">Nurse</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-person-check me-1"></i> Register
                </button>
            </form>

            <div class="text-center mt-3 small">
                Already have an account?
                <a href="/login.php">Sign in</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auth.js"></script>
</body>
</html>
