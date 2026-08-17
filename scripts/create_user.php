<?php
require __DIR__ . "/../vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->safeLoad();

require __DIR__ . "/../public/bootstrap.php";

use App\Services\FirebaseAuthService;
use App\Services\FirebaseService;

// Usage: php scripts/create_user.php "Full Name" email@example.com [role] [houseId] [password]
$argv = $_SERVER['argv'];
array_shift($argv); // script name

if (count($argv) < 2) {
    echo "Usage: php scripts/create_user.php \"Full Name\" email@example.com [role] [houseId] [password]\n";
    exit(1);
}

$name = $argv[0];
$email = $argv[1];
$role = $argv[2] ?? 'houseparent';
$houseId = $argv[3] ?? null;
$password = $argv[4] ?? 'Password@123';

// Create authentication record via Firebase REST API (FirebaseAuthService)
// Try to create an auth user; if it fails (missing API key or network), fall back to a local UID.
$signup = FirebaseAuthService::signUp($email, $password);
if ($signup && isset($signup['uid'])) {
    $uid = $signup['uid'];
    $createdViaAuth = true;
} else {
    $uid = 'local-' . bin2hex(random_bytes(8));
    $createdViaAuth = false;
    echo "Note: Firebase auth signup failed or unavailable; using local UID {$uid}.\n";
}

// Add Firestore profile via FirebaseService
// No local fallback - Firebase is required
$fs = FirebaseService::getInstance();
try {
    $fs->addDocument('users', [
        'uid' => $uid,
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'houseId' => $houseId,
        'status' => 'active'
    ], $uid);
    echo "User created successfully. UID: {$uid}\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Error: Failed to create user in Firestore.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Ensure FIREBASE_ENABLED=true and valid credentials file is set in .env\n";
    exit(1);
}
