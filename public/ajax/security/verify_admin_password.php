<?php
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../app/config/constants.php';
$appConfig = require __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

use App\Services\FirebaseAuthService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_login();
require_role('admin');

// Rate-limiting: track failed attempts per IP
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'reauth_attempts_' . hash('md5', $clientIp);
$attempts = $_SESSION[$rateLimitKey] ?? ['count' => 0, 'first_at' => time()];

// Reset if window is older than 15 minutes
if (time() - $attempts['first_at'] > 900) {
    $attempts = ['count' => 0, 'first_at' => time()];
}

// Check if rate limited (5 failed attempts)
if ($attempts['count'] >= 5) {
    http_response_code(429);
    $retryAfter = max(0, 900 - (time() - $attempts['first_at']));
    echo json_encode(['ok' => false, 'error' => 'Too many attempts. Please try again later.', 'retry_after' => $retryAfter]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$password = (string) ($data['password'] ?? '');

$user = current_user();
$email = $user['email'] ?? '';

// Default admin shortcut
$defaultAdminEmail = $_ENV['DEFAULT_ADMIN_EMAIL'] ?? '';
$defaultAdminPassword = $_ENV['DEFAULT_ADMIN_PASSWORD'] ?? '';
if ($defaultAdminEmail && $defaultAdminPassword && $email === $defaultAdminEmail) {
    if ($password === $defaultAdminPassword) {
        // Success: reset attempts and set re-auth timestamp
        $_SESSION[$rateLimitKey] = ['count' => 0, 'first_at' => time()];
        $_SESSION['reauth_verified_at'] = time();
        echo json_encode(['ok' => true]);
        exit;
    }
    // Failed attempt
    $attempts['count']++;
    $_SESSION[$rateLimitKey] = $attempts;
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
    exit;
}

// Verify via Firebase
if (!empty($appConfig['firebase_enabled'])) {
    $res = FirebaseAuthService::signIn($email, $password);
    if ($res) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'first_at' => time()];
        $_SESSION['reauth_verified_at'] = time();
        echo json_encode(['ok' => true]);
        exit;
    }
}

// All checks failed: increment counter
$attempts['count']++;
$_SESSION[$rateLimitKey] = $attempts;
echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
