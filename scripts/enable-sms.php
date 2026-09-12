<?php
/**
 * Quick script to enable SMS notifications in Firebase
 * Run with: php scripts/enable-sms.php
 */

if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/public/bootstrap.php')) {
            require $dir . '/public/bootstrap.php';
            break;
        }
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}

if (!defined('APP_ROOT')) {
    die("ERROR: Could not find bootstrap.php\n");
}

use App\Services\FirebaseService;

try {
    echo "[*] Connecting to Firebase...\n";
    $firebase = FirebaseService::getInstance();
    
    echo "[*] Retrieving current settings...\n";
    $settings = $firebase->getDocument(COL_SETTINGS, 'global') ?? [];
    $values = $settings['values'] ?? [];
    
    echo "[*] Current sms_notifications: " . ($values['sms_notifications'] ?? 'NOT SET') . "\n";
    
    // Update SMS notifications to enabled
    $values['sms_notifications'] = '1';
    
    echo "[*] Updating Firebase document...\n";
    $firebase->updateDocument(COL_SETTINGS, 'global', [
        'values' => $values,
        'updatedAt' => date(DATE_ATOM),
        'updatedBy' => 'sms-enable-script',
    ]);
    
    echo "[✓] SUCCESS! SMS notifications enabled in Firebase.\n";
    echo "[✓] SMS will now be sent when exeat is created/approved/departed/returned.\n";
    
} catch (Throwable $e) {
    echo "[✗] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
