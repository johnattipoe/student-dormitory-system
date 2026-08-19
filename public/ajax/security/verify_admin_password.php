<?php
require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

use App\Services\FirebaseAuthService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

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
