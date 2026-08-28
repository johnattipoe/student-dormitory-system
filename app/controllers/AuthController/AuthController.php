<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\FirebaseAuthService;

if (!class_exists(AuthService::class, false)) {
    require_once __DIR__ . '/../../services/AuthService/AuthService.php';
}

if (!class_exists(FirebaseAuthService::class, false)) {
    require_once __DIR__ . '/../../services/FirebaseAuthService/FirebaseAuthService.php';
}

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function showLogin(): void
    {
        if (AuthService::isLoggedIn()) {
            redirect(base_url(ROLE_DASHBOARD[AuthService::role()] ?? '/views/dashboard/dashboard.php'));
        }
        include __DIR__ . '/../../../public/views/auth/login/login.php';
    }

    public function login(): void
    {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = validate_required(['email' => $email, 'password' => $password], ['email', 'password']);
        if (!empty($errors) || !validate_email($email)) {
            flash('error', 'Please enter a valid email and password.');
            redirect(base_url('login.php'));
        }

        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('login.php'));
        }

        $role = $result['user']['role'];
        $target = ROLE_DASHBOARD[$role] ?? '/views/dashboard/dashboard.php';
        redirect(base_url('index.php?route=' . urlencode($target)));
    }

    public function showForgotPassword(): void
    {
        if (AuthService::isLoggedIn()) {
            redirect(base_url(ROLE_DASHBOARD[AuthService::role()] ?? '/views/dashboard/dashboard.php'));
        }

        include __DIR__ . '/../../../public/views/auth/forgot-password/forgot-password.php';
    }

    public function forgotPassword(): void
    {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email) || !validate_email($email)) {
            flash('error', 'Please enter a valid email address.');
            redirect(base_url('forgot-password.php'));
        }

        $result = FirebaseAuthService::sendPasswordResetEmail($email);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('forgot-password.php'));
        }

        flash('success', 'Password reset instructions were sent to ' . $email . '.');
        redirect(base_url('forgot-password.php'));
    }

    public function showResetPassword(): void
    {
        if (AuthService::isLoggedIn()) {
            redirect(base_url(ROLE_DASHBOARD[AuthService::role()] ?? '/views/dashboard/dashboard.php'));
        }

        include __DIR__ . '/../../../public/views/auth/reset-password/reset-password.php';
    }

    public function resetPassword(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            flash('error', 'Reset token is missing or invalid.');
            redirect(base_url('reset-password.php'));
        }

        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters long.');
            redirect(base_url('reset-password.php?token=' . urlencode($token)));
        }

        if ($password !== $confirmPassword) {
            flash('error', 'Passwords do not match. Please try again.');
            redirect(base_url('reset-password.php?token=' . urlencode($token)));
        }

        $result = FirebaseAuthService::confirmPasswordReset($token, $password);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('reset-password.php?token=' . urlencode($token)));
        }

        flash('success', 'Your password has been reset successfully. Please sign in.');
        redirect(base_url('login.php'));
    }

    public function showVerifyAccount(): void
    {
        if (AuthService::isLoggedIn()) {
            redirect(base_url(ROLE_DASHBOARD[AuthService::role()] ?? '/views/dashboard/dashboard.php'));
        }

        include __DIR__ . '/../../../public/views/auth/verify-account/verify-account.php';
    }

    public function verifyAccount(): void
    {
        $email = sanitize($_POST['email'] ?? '');
        $code = trim((string) ($_POST['verification_code'] ?? ''));

        if (!empty($email) && !empty($code)) {
            $result = FirebaseAuthService::sendEmailVerification($email);

            if (!$result['success']) {
                flash('error', $result['message']);
                redirect(base_url('verify-account.php'));
            }

            flash('success', 'A verification email has been sent to ' . $email . '. Follow the link to complete verification.');
            redirect(base_url('verify-account.php'));
        }

        if (empty($code) || strlen($code) < 6) {
            flash('error', 'Please enter a valid verification code.');
            redirect(base_url('verify-account.php'));
        }

        flash('success', 'Your account has been verified successfully.');
        redirect(base_url('login.php'));
    }

    public function logout(): void
    {
        $this->authService->logout();
        redirect(base_url('login.php'));
    }
}
