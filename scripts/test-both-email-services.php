<?php
/**
 * Dual Email Service Test
 * Tests both Gmail SMTP and SendGrid to verify both are working
 * Usage: php scripts/test-both-email-services.php
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

use App\Services\EmailService;
use App\Services\SendGridEmailService;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  DUAL EMAIL SERVICE TEST (SendGrid + Gmail SMTP)          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load configuration
$config = require APP_ROOT . '/app/config/mail/mail.php';

echo "📋 Current Configuration:\n";
echo "  Host: " . ($config['host'] ?? 'NOT SET') . "\n";
echo "  Port: " . ($config['port'] ?? 'NOT SET') . "\n";
echo "  From: " . ($config['from_address'] ?? 'NOT SET') . "\n";
echo "  Enabled: " . ($config['enabled'] ? 'YES' : 'NO') . "\n\n";

$testEmail = $config['from_address'];
if (empty($testEmail)) {
    echo "❌ No FROM email configured. Cannot test.\n";
    exit(1);
}

// ============================================
// TEST 1: SendGrid Web API
// ============================================
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST 1️⃣ : SendGrid Web API\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$sendgridResult = [
    'success' => false,
    'message' => 'Not tested',
    'exception' => null,
];

try {
    $sendGridService = new SendGridEmailService();
    echo "✅ SendGrid service initialized successfully\n";
    echo "  Testing email delivery...\n\n";
    
    $sendgridResult = $sendGridService->sendHtml(
        $testEmail,
        '[TEST] SendGrid Web API Test',
        '<h2>SendGrid Test Email</h2><p>If you received this, SendGrid is working!</p><p>Test sent from: ' . gethostname() . '</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>'
    );
} catch (\Throwable $e) {
    $sendgridResult['exception'] = $e->getMessage();
    echo "❌ SendGrid service initialization failed:\n";
    echo "   " . $e->getMessage() . "\n\n";
}

if ($sendgridResult['success']) {
    echo "✅ SENDGRID SUCCESS\n";
    echo "   Message: " . $sendgridResult['message'] . "\n";
} else {
    echo "❌ SENDGRID FAILED\n";
    echo "   Reason: " . ($sendgridResult['message'] ?? $sendgridResult['exception'] ?? 'Unknown error') . "\n";
}

echo "\n";

// ============================================
// TEST 2: Gmail SMTP
// ============================================
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST 2️⃣ : Gmail SMTP (EmailService)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// For Gmail, we need to check the config - it might not be set for the current host
$gmailHost = 'smtp.gmail.com';
$gmailPort = 587;
$gmailUsername = $config['username'] ?? '';
$gmailPassword = $config['password'] ?? '';

// Check if we should use Gmail credentials (if they differ from SendGrid)
// For now, assume we keep Gmail credentials available
// We need to test with Gmail credentials, not SendGrid credentials

echo "🔍 Checking Gmail Connectivity...\n";

// Try to connect to Gmail SMTP
$fp = @fsockopen($gmailHost, $gmailPort, $errno, $errstr, 5);
if ($fp) {
    fclose($fp);
    echo "  ✅ Port 587 (TLS) is open to gmail.com\n\n";
} else {
    echo "  ❌ Cannot connect to gmail.com:587 (Error: $errstr)\n";
    echo "     This is expected on Render (firewall blocks it)\n\n";
}

$gmailResult = [
    'success' => false,
    'message' => 'Not tested',
];

// Try to send via Gmail
echo "📨 Attempting to send test email via Gmail SMTP...\n\n";

$emailService = new EmailService();
$gmailResult = $emailService->sendHtml(
    $testEmail,
    '[TEST] Gmail SMTP Test',
    '<h2>Gmail SMTP Test Email</h2><p>If you received this, Gmail SMTP is working!</p><p>Test sent from: ' . gethostname() . '</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>'
);

if ($gmailResult['success']) {
    echo "✅ GMAIL SUCCESS\n";
    echo "   Message: " . $gmailResult['message'] . "\n";
} else {
    echo "❌ GMAIL FAILED\n";
    echo "   Reason: " . ($gmailResult['message'] ?? 'Unknown error') . "\n";
}

echo "\n";

// ============================================
// SUMMARY & RECOMMENDATIONS
// ============================================
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 SUMMARY\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$sendgridOK = $sendgridResult['success'];
$gmailOK = $gmailResult['success'];

echo "SendGrid Web API: " . ($sendgridOK ? "✅ WORKING" : "❌ NOT WORKING") . "\n";
echo "Gmail SMTP:       " . ($gmailOK ? "✅ WORKING" : "❌ NOT WORKING") . "\n\n";

if ($sendgridOK && $gmailOK) {
    echo "🎉 EXCELLENT! Both services are working!\n";
    echo "   → SendGrid will be used as primary\n";
    echo "   → Gmail SMTP will be used as fallback\n";
    echo "   → Maximum email reliability achieved!\n";
} elseif ($sendgridOK) {
    echo "✅ SENDGRID IS WORKING\n";
    echo "   → Your primary email service is ready\n";
    echo "   → Gmail not available (normal on Render due to firewall)\n";
} elseif ($gmailOK) {
    echo "✅ GMAIL IS WORKING\n";
    echo "   → Your email service is ready\n";
    echo "   → SendGrid not configured (optional, Gmail is sufficient)\n";
} else {
    echo "❌ PROBLEM: Neither service is working!\n";
    echo "\n   📋 Troubleshooting:\n";
    echo "      1. Check .env file has correct credentials\n";
    echo "      2. Verify MAIL_ENABLED=true\n";
    echo "      3. For SendGrid: ensure API key starts with 'SG.'\n";
    echo "      4. For Gmail: verify app password is correct\n";
    echo "      5. Check internet connection\n";
}

echo "\n";
?>
