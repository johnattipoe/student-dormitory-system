<?php
$defaultSettings = [
    'name' => $_ENV['APP_NAME'] ?? 'Student Dormitory System',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
    'attendance_grace_minutes' => 10,
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos',
    'default_password' => 'Dorm1234',
    'support_email' => 'support@dormitory.local',
    'allow_self_registration' => true,
    'require_email_verification' => true,
    'enable_notifications' => true,
    'firebase_enabled' => filter_var($_ENV['FIREBASE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'show_local_passwords' => false,
];

// Admin settings are now managed through Firestore (settings collection)
// No local JSON file fallback

$resolvedTimezone = $defaultSettings['timezone'] ?? ($_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos');
if (is_string($resolvedTimezone) && $resolvedTimezone !== '') {
    date_default_timezone_set($resolvedTimezone);
}

return [
    'name' => $defaultSettings['app_name'] ?? $defaultSettings['name'],
    'env' => $defaultSettings['env'] ?? ($_ENV['APP_ENV'] ?? 'local'),
    'url' => $defaultSettings['app_url'] ?? $defaultSettings['url'],
    'debug' => isset($defaultSettings['debug']) ? filter_var($defaultSettings['debug'], FILTER_VALIDATE_BOOLEAN) : (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)),
    'session_lifetime' => (int) ($defaultSettings['session_timeout'] ?? $defaultSettings['session_lifetime'] ?? ($_ENV['SESSION_LIFETIME'] ?? 120)),
    'attendance_grace_minutes' => (int) ($defaultSettings['attendance_grace_minutes'] ?? 10),
    'timezone' => $resolvedTimezone,
    'default_password' => $defaultSettings['default_password'] ?? 'Dorm1234',
    'support_email' => $defaultSettings['support_email'] ?? 'support@dormitory.local',
    'allow_self_registration' => !empty($defaultSettings['allow_self_registration']) && $defaultSettings['allow_self_registration'] !== '0',
    'require_email_verification' => !empty($defaultSettings['require_email_verification']) && $defaultSettings['require_email_verification'] !== '0',
    'enable_notifications' => !empty($defaultSettings['enable_notifications']) && $defaultSettings['enable_notifications'] !== '0',
    'firebase_enabled' => !empty($defaultSettings['firebase_enabled']) && $defaultSettings['firebase_enabled'] !== '0',
    'show_local_passwords' => !empty($defaultSettings['show_local_passwords']) && $defaultSettings['show_local_passwords'] !== '0',
];
