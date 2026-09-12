<?php
/**
 * Email Configuration Test Script
 * Usage: php scripts/test-email.php
 * 
 * This script tests SMTP connectivity and email delivery on any host (localhost, Render, etc.)
 */

// Define BASE_PATH before requiring bootstrap
define('BASE_PATH', __DIR__ . '/..');

if (!defined('APP_ROOT')) {
    $dir = BASE_PATH;
    if (file_exists($dir . '/public/bootstrap.php')) {
        require $dir . '/public/bootstrap.php';
    } elseif (file_exists($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
    }
}

use App\Services\EmailService;
use App\Services\SendGridEmailService;

echo "=== Email Configuration Test ===\n\n";

// Load configuration
$config = require APP_ROOT . '/app/config/mail/mail.php';

echo "📧 Current Configuration:\n";
echo "  Host: " . ($config['host'] ?? 'NOT SET') . "\n";
echo "  Port: " . ($config['port'] ?? 'NOT SET') . "\n";
echo "  Username: " . ($config['username'] ?? 'NOT SET') . "\n";
echo "  Encryption: " . ($config['encryption'] ?? 'NOT SET') . "\n";
echo "  From: " . ($config['from_address'] ?? 'NOT SET') . "\n";
echo "  Enabled: " . ($config['enabled'] ? 'YES' : 'NO') . "\n\n";

if (!$config['enabled']) {
    echo "❌ Email is disabled. Set MAIL_ENABLED=true in .env\n";
    exit(1);
}

if (empty($config['host']) || empty($config['from_address'])) {
    echo "❌ Mail host and from_address are required.\n";
    exit(1);
}

// Detect which service to use
$isUsingSendGrid = strpos($config['host'] ?? '', 'sendgrid') !== false;

if ($isUsingSendGrid) {
    echo "📨 Using SendGrid Web API (no sender verification needed)...\n\n";
    $testEmail = $config['from_address'];
    echo "  Sending test email to: $testEmail\n\n";
    
    try {
        $emailService = new SendGridEmailService();
        $result = $emailService->sendHtml(
            $testEmail,
            'SendGrid Email Configuration Test',
            '<h2>Email Test Successful!</h2><p>If you received this, your SendGrid configuration is working correctly.</p><p>Test sent from: ' . gethostname() . '</p>'
        );
    } catch (\Throwable $e) {
        $result = ['success' => false, 'message' => 'Service initialization failed: ' . $e->getMessage()];
    }
} else {
    // Test SMTP connectivity
    echo "🔌 Testing SMTP Connectivity...\n";

    $ports = [$config['port']];
    if ($config['host'] === 'smtp.gmail.com' && $config['port'] == 587) {
        $ports[] = 465;
    }

    foreach ($ports as $testPort) {
        $encryption = $testPort === 465 ? 'SSL' : 'TLS';
        $fp = @fsockopen($config['host'], $testPort, $errno, $errstr, 5);
        
        if ($fp) {
            fclose($fp);
            echo "  ✅ Port $testPort ($encryption): OPEN\n";
        } else {
            echo "  ❌ Port $testPort ($encryption): CLOSED/BLOCKED (Error: $errstr)\n";
        }
    }

    echo "\n📨 Testing Email Delivery via SMTP...\n";

    $testEmail = $config['from_address'];
    echo "  Sending test email to: $testEmail\n\n";

    $emailService = new EmailService();
    $result = $emailService->sendHtml(
        $testEmail,
        'Email Configuration Test',
        '<h2>Email Test Successful!</h2><p>If you received this, your email configuration is working correctly on this host.</p><p>Test sent from: ' . gethostname() . '</p>'
    );
}

if ($result['success']) {
    echo "✅ SUCCESS: " . $result['message'] . "\n";
    exit(0);
} else {
    echo "❌ FAILED: " . $result['message'] . "\n";
    echo "\n📋 Troubleshooting:\n";
    if ($isUsingSendGrid) {
        echo "  1. Verify SendGrid API key in .env is correct\n";
        echo "  2. Check MAIL_USERNAME is set to 'apikey'\n";
        echo "  3. Ensure .env file in root directory\n";
    } else {
        echo "  1. If port 587 is blocked, try port 465 (SSL)\n";
        echo "  2. If using Gmail, ensure App Password (not regular password)\n";
        echo "  3. On Render, switch to SendGrid (less firewall issues)\n";
        echo "  4. Check .env file is in root directory\n";
        echo "  5. Ensure MAIL_USERNAME and MAIL_PASSWORD have no extra spaces\n";
    }
    exit(1);
}
?>
