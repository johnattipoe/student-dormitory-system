<?php
/**
 * Environment Variable Diagnostic
 * Run this on Render to debug configuration issues
 */

define('BASE_PATH', __DIR__ . '/..');

if (!defined('APP_ROOT')) {
    $dir = BASE_PATH;
    if (file_exists($dir . '/public/bootstrap.php')) {
        require $dir . '/public/bootstrap.php';
    } elseif (file_exists($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
    }
}

echo "=== Environment Variable Diagnostic ===\n\n";

echo "📍 Running on: " . gethostname() . "\n";
echo "📍 PHP Version: " . phpversion() . "\n";
echo "📍 APP_ROOT: " . (defined('APP_ROOT') ? APP_ROOT : 'NOT DEFINED') . "\n\n";

// Check if .env file exists
$envFiles = [
    APP_ROOT . '/.env',
    APP_ROOT . '/.env.local',
];

echo "📋 .env Files:\n";
foreach ($envFiles as $file) {
    if (file_exists($file)) {
        echo "  ✅ $file (exists, " . filesize($file) . " bytes)\n";
    } else {
        echo "  ❌ $file (not found)\n";
    }
}

echo "\n";

// Load mail config
echo "📧 Mail Configuration (from app/config/mail/mail.php):\n";
try {
    $config = require APP_ROOT . '/app/config/mail/mail.php';
    echo "  Host: " . ($config['host'] ?? 'NOT SET') . "\n";
    echo "  Port: " . ($config['port'] ?? 'NOT SET') . "\n";
    echo "  Username: " . ($config['username'] ?? 'NOT SET') . "\n";
    echo "  Encryption: " . ($config['encryption'] ?? 'NOT SET') . "\n";
    echo "  From Address: " . ($config['from_address'] ?? 'NOT SET') . "\n";
    echo "  From Name: " . ($config['from_name'] ?? 'NOT SET') . "\n";
    echo "  Enabled: " . ($config['enabled'] ? 'YES' : 'NO') . "\n";
    
    $password = (string) ($config['password'] ?? '');
    if (empty($password)) {
        echo "  Password: [EMPTY - THIS IS THE PROBLEM]\n";
    } elseif (strlen($password) < 10) {
        echo "  Password: [TOO SHORT - length: " . strlen($password) . "]\n";
    } else {
        echo "  Password: [SET - length: " . strlen($password) . ", starts with: " . substr($password, 0, 5) . "...]\n";
    }
} catch (\Throwable $e) {
    echo "  ❌ Error loading config: " . $e->getMessage() . "\n";
}

echo "\n";

// Check environment variables directly
echo "🔍 Environment Variables (from getenv()):\n";
$envVars = [
    'MAIL_ENABLED',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_ENCRYPTION',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME',
];

foreach ($envVars as $var) {
    $val = getenv($var);
    if ($val === false) {
        echo "  ❌ $var: NOT SET\n";
    } else {
        if (strlen($val) < 10) {
            echo "  ✅ $var: $val\n";
        } else {
            echo "  ✅ $var: [SET - length: " . strlen($val) . "]\n";
        }
    }
}

echo "\n";

// Check $_ENV and $_SERVER
echo "📦 Super Globals:\n";
echo "  \$_ENV count: " . count($_ENV) . "\n";
echo "  \$_SERVER['SERVER_NAME']: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET') . "\n";

echo "\n";

// Summary
echo "💡 Summary:\n";
$config = require APP_ROOT . '/app/config/mail/mail.php';
if (empty($config['password']) || !str_starts_with($config['password'], 'SG.')) {
    echo "  ⚠️  SendGrid API key is not configured in Render environment.\n";
    echo "\n  SOLUTION: Set these environment variables on Render:\n";
    echo "    MAIL_ENABLED=true\n";
    echo "    MAIL_HOST=smtp.sendgrid.net\n";
    echo "    MAIL_PORT=587\n";
    echo "    MAIL_USERNAME=apikey\n";
    echo "    MAIL_PASSWORD=SG.your_api_key_here\n";
    echo "    MAIL_FROM_ADDRESS=johnattipoe1@gmail.com\n";
    echo "    MAIL_FROM_NAME=Student Dormitory System\n";
} else {
    echo "  ✅ Mail configuration looks good!\n";
}
?>
