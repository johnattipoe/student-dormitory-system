<?php
$defaultSettings = [
    'app_name' => $_ENV['APP_NAME'] ?? 'Student Dormitory System',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'session_timeout' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
    'attendance_grace_minutes' => 10,
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos',
    'default_password' => 'Dorm1234',
    'password_min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
    'password_require_mixed_case' => filter_var($_ENV['PASSWORD_REQUIRE_MIXED_CASE'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'support_email' => 'support@dormitory.local',
    'allow_self_registration' => '1',
    'require_email_verification' => '1',
    'enable_notifications' => '1',
    'firebase_enabled' => filter_var($_ENV['FIREBASE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'show_local_passwords' => '1',
    'default_role' => 'student',
    'registration_requires_approval' => '1',
    'max_login_attempts' => '5',
    'account_lockout_minutes' => '15',
    'session_regenerate_interval' => (int) ($_ENV['SESSION_REGENERATE_INTERVAL'] ?? 15),
    'pagination_per_page' => (int) ($_ENV['PAGINATION_PER_PAGE'] ?? 25),
    'export_max_records' => (int) ($_ENV['EXPORT_MAX_RECORDS'] ?? 5000),
    'import_max_records' => (int) ($_ENV['IMPORT_MAX_RECORDS'] ?? 1000),
    'max_upload_size_mb' => (int) ($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 5),
    'allowed_upload_extensions' => $_ENV['ALLOWED_UPLOAD_EXTENSIONS'] ?? 'jpg,jpeg,png,pdf,csv,xlsx',
    'check_in_time' => '14:00',
    'check_out_time' => '12:00',
    'curfew_time' => '22:00',
    'visitor_approval_required' => '1',
    'overstay_alert_hours' => '24',
    'maximum_room_occupancy' => '4',
    'late_arrival_minutes' => '15',
    'auto_mark_absent' => '1',
    'weekend_attendance' => '1',
    'email_notifications' => '1',
    'sms_notifications' => '1',
    'sms_max_length' => 160,
    'emergency_response_minutes' => (int) ($_ENV['EMERGENCY_RESPONSE_MINUTES'] ?? 15),
    'emergency_follow_up_hours' => (int) ($_ENV['EMERGENCY_FOLLOW_UP_HOURS'] ?? 24),
    'audit_log_retention_days' => (int) ($_ENV['AUDIT_LOG_RETENTION_DAYS'] ?? 365),
    'notification_recipient_roles' => 'admin,housemaster',
    'reminder_time' => '08:00',
    'maintenance_mode' => '0',
    'institution_logo_url' => '',
    'primary_color' => '#1f6feb',
    'footer_text' => 'Student Dormitory System',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
];

// Firestore is optional during CLI tasks, first-run setup, and local development.
if (!defined('SKIP_REMOTE_SETTINGS') && defined('APP_ROOT') && class_exists(\App\Services\FirebaseService::class)) {
    try {
        $savedSettings = \App\Services\FirebaseService::getInstance()->getDocument(COL_SETTINGS, 'global');
        if (!empty($savedSettings['values']) && is_array($savedSettings['values'])) {
            $defaultSettings = array_merge($defaultSettings, $savedSettings['values']);
        }
    } catch (Throwable $e) {
        // Environment defaults remain usable when Firestore is unavailable.
    }
}

$resolvedTimezone = $defaultSettings['timezone'] ?? ($_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos');
if (is_string($resolvedTimezone) && $resolvedTimezone !== '') {
    date_default_timezone_set($resolvedTimezone);
}

return [
    'name' => $defaultSettings['app_name'],
    'env' => $defaultSettings['env'] ?? ($_ENV['APP_ENV'] ?? 'local'),
    'url' => $defaultSettings['app_url'],
    'debug' => filter_var($defaultSettings['debug'], FILTER_VALIDATE_BOOLEAN),
    'session_lifetime' => (int) $defaultSettings['session_timeout'],
    'password_min_length' => max(6, (int) ($defaultSettings['password_min_length'] ?? 8)),
    'password_require_mixed_case' => filter_var($defaultSettings['password_require_mixed_case'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'session_regenerate_interval' => max(0, (int) ($defaultSettings['session_regenerate_interval'] ?? 15)),
    'pagination_per_page' => max(1, min(100, (int) ($defaultSettings['pagination_per_page'] ?? 25))),
    'export_max_records' => max(1, (int) ($defaultSettings['export_max_records'] ?? 5000)),
    'import_max_records' => max(1, (int) ($defaultSettings['import_max_records'] ?? 1000)),
    'max_upload_size_mb' => max(1, min(50, (int) ($defaultSettings['max_upload_size_mb'] ?? 5))),
    'allowed_upload_extensions' => array_values(array_filter(array_map('trim', explode(',', (string) ($defaultSettings['allowed_upload_extensions'] ?? 'jpg,jpeg,png,pdf,csv,xlsx'))))),
    'attendance_grace_minutes' => (int) ($defaultSettings['attendance_grace_minutes'] ?? 10),
    'timezone' => $resolvedTimezone,
    'default_password' => $defaultSettings['default_password'] ?? 'Dorm1234',
    'support_email' => $defaultSettings['support_email'] ?? 'support@dormitory.local',
    'allow_self_registration' => !empty($defaultSettings['allow_self_registration']) && $defaultSettings['allow_self_registration'] !== '0',
    'require_email_verification' => !empty($defaultSettings['require_email_verification']) && $defaultSettings['require_email_verification'] !== '0',
    'enable_notifications' => !empty($defaultSettings['enable_notifications']) && $defaultSettings['enable_notifications'] !== '0',
    'firebase_enabled' => !empty($defaultSettings['firebase_enabled']) && $defaultSettings['firebase_enabled'] !== '0',
    'sms_max_length' => max(1, (int) ($defaultSettings['sms_max_length'] ?? 160)),
    'emergency_response_minutes' => max(1, (int) ($defaultSettings['emergency_response_minutes'] ?? 15)),
    'emergency_follow_up_hours' => max(1, (int) ($defaultSettings['emergency_follow_up_hours'] ?? 24)),
    'audit_log_retention_days' => max(1, (int) ($defaultSettings['audit_log_retention_days'] ?? 365)),
    'show_local_passwords' => !empty($defaultSettings['show_local_passwords']) && $defaultSettings['show_local_passwords'] !== '0',
    'advanced' => $defaultSettings,
];
