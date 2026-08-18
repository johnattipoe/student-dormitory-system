<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Settings';
$appConfig = require APP_ROOT . '/app/config/app.php';
$defaultSettings = [
    'app_name' => $appConfig['name'] ?? 'Student Dormitory System',
    'app_url' => $appConfig['url'] ?? 'https://student-dormitory-system.onrender.com',
    'session_timeout' => $appConfig['session_lifetime'] ?? '60',
    'attendance_grace_minutes' => $appConfig['attendance_grace_minutes'] ?? '10',
    'allow_self_registration' => $appConfig['allow_self_registration'] ? '1' : '0',
    'require_email_verification' => $appConfig['require_email_verification'] ? '1' : '0',
    'enable_notifications' => $appConfig['enable_notifications'] ? '1' : '0',
    'firebase_enabled' => $appConfig['firebase_enabled'] ? '1' : '0',
    'default_password' => $appConfig['default_password'] ?? 'Dorm1234',
    'support_email' => $appConfig['support_email'] ?? 'support@dormitory.local',
    'timezone' => $appConfig['timezone'] ?? 'Africa/Lagos',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'app_name' => trim((string) ($_POST['app_name'] ?? $defaultSettings['app_name'])),
        'app_url' => trim((string) ($_POST['app_url'] ?? $defaultSettings['app_url'])),
        'session_timeout' => (string) (int) ($_POST['session_timeout'] ?? $defaultSettings['session_timeout']),
        'attendance_grace_minutes' => (string) (int) ($_POST['attendance_grace_minutes'] ?? $defaultSettings['attendance_grace_minutes']),
        'allow_self_registration' => !empty($_POST['allow_self_registration']) ? '1' : '0',
        'require_email_verification' => !empty($_POST['require_email_verification']) ? '1' : '0',
        'enable_notifications' => !empty($_POST['enable_notifications']) ? '1' : '0',
        'firebase_enabled' => !empty($_POST['firebase_enabled']) ? '1' : '0',
        'default_password' => trim((string) ($_POST['default_password'] ?? $defaultSettings['default_password'])),
        'support_email' => trim((string) ($_POST['support_email'] ?? $defaultSettings['support_email'])),
        'timezone' => trim((string) ($_POST['timezone'] ?? $defaultSettings['timezone'])),
    ];

    // Settings managed via Firestore (settings collection)
    // TODO: Implement Firebase write for admin settings
    flash('info', 'Settings management via Firestore coming soon.');
    redirect(url('views/admin/settings/index.php'));
}

$settings = $defaultSettings;
// Load custom settings from Firestore when integration is complete

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/admin/settings/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-0">Settings</h5>
                <p class="text-muted mb-0">Configure system-wide admin settings and preferences.</p>
            </div>
            <button type="submit" form="settings-form" class="btn btn-primary btn-sm">Save Settings</button>
        </div>

        <form id="settings-form" method="POST" action="<?= url('views/admin/settings/index.php') ?>">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card stat-card p-3 h-100">
                        <h6 class="mb-3">General Settings</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Application Name</label>
                                <input name="app_name" class="form-control" value="<?= e($settings['app_name']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Application URL</label>
                                <input name="app_url" class="form-control" value="<?= e($settings['app_url']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" min="5" name="session_timeout" class="form-control" value="<?= e((string) ($settings['session_timeout'] ?? '60')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Attendance Grace (minutes)</label>
                                <input type="number" min="0" name="attendance_grace_minutes" class="form-control" value="<?= e((string) ($settings['attendance_grace_minutes'] ?? '10')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Timezone</label>
                                <input name="timezone" class="form-control" value="<?= e($settings['timezone']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support Email</label>
                                <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card stat-card p-3 h-100">
                        <h6 class="mb-3">Security & Access</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Default Temporary Password</label>
                                <input name="default_password" class="form-control" value="<?= e($settings['default_password']) ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_self_registration" id="allow_self_registration" value="1" <?= !empty($settings['allow_self_registration']) && $settings['allow_self_registration'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allow_self_registration">Allow self-registration</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_email_verification" id="require_email_verification" value="1" <?= !empty($settings['require_email_verification']) && $settings['require_email_verification'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="require_email_verification">Require email verification</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?= !empty($settings['enable_notifications']) && $settings['enable_notifications'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_notifications">Enable system notifications</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="firebase_enabled" id="firebase_enabled" value="1" <?= !empty($settings['firebase_enabled']) && $settings['firebase_enabled'] !== '0' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="firebase_enabled">Enable Firebase sync</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>