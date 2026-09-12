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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Settings';
$appConfig = require APP_ROOT . '/app/config/app/app.php';
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

    try {
        FirebaseService::getInstance()->addDocument(COL_SETTINGS, [
            'values' => $settings,
            'updatedBy' => current_user()['uid'] ?? current_user()['id'] ?? 'admin',
        ], 'global');
        flash('success', 'Settings saved successfully.');
    } catch (Throwable $e) {
        flash('error', 'Unable to save settings: ' . $e->getMessage());
    }
    redirect(url('views/admin/settings/index/index.php'));
}

$settings = $defaultSettings;
$settingsSource = 'Application defaults';
$settingsConnected = false;
try {
    $savedSettings = FirebaseService::getInstance()->getDocument(COL_SETTINGS, 'global');
    if (!empty($savedSettings['values']) && is_array($savedSettings['values'])) {
        $settings = array_merge($settings, $savedSettings['values']);
        $settingsSource = 'Firebase settings/global';
        $settingsConnected = true;
    }
} catch (Throwable $e) {
    // Keep the config defaults available when Firestore is unavailable.
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/admin/settings/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-gear-fill text-secondary me-2"></i>System Configuration &amp; Settings
                </h4>
                <p class="text-muted mb-0">Control application identity, authentication parameters, security, and integration defaults</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/settings/advanced/advanced.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-sliders2 me-1"></i> Advanced Settings
                </a>
                <button type="submit" form="settings-form" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Save Settings
                </button>
            </div>
        </div>

        <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4 py-2">
            <span class="small text-muted"><i class="bi bi-cloud-check me-1 text-primary"></i> Data Source: <strong><?= e($settingsSource) ?></strong></span>
            <span class="badge bg-<?= $settingsConnected ? 'success' : 'secondary' ?>"><?= $settingsConnected ? 'Live Cloud Sync' : 'Using Local Config' ?></span>
        </div>

        <form id="settings-form" method="POST" action="<?= url('views/admin/settings/index/index.php') ?>">
            <div class="row g-4">
                <!-- General Settings -->
                <div class="col-lg-6">
                    <div class="card stat-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>General &amp; Regional Preferences</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Application Name</label>
                                    <input name="app_name" class="form-control" value="<?= e($settings['app_name']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Application Base URL</label>
                                    <input name="app_url" class="form-control" value="<?= e($settings['app_url']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Session Lifetime (minutes)</label>
                                    <input type="number" min="5" name="session_timeout" class="form-control" value="<?= e((string) ($settings['session_timeout'] ?? '60')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Attendance Grace (minutes)</label>
                                    <input type="number" min="0" name="attendance_grace_minutes" class="form-control" value="<?= e((string) ($settings['attendance_grace_minutes'] ?? '10')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Timezone</label>
                                    <input name="timezone" class="form-control" value="<?= e($settings['timezone']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Support Contact Email</label>
                                    <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security & Auth Settings -->
                <div class="col-lg-6">
                    <div class="card stat-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-danger"></i>Security &amp; User Authentication</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Default Student/User Password</label>
                                    <input name="default_password" class="form-control" value="<?= e($settings['default_password']) ?>">
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="allow_self_registration" id="allow_self_registration" value="1" <?= !empty($settings['allow_self_registration']) && $settings['allow_self_registration'] !== '0' ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold small" for="allow_self_registration">Allow portal self-registration</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="require_email_verification" id="require_email_verification" value="1" <?= !empty($settings['require_email_verification']) && $settings['require_email_verification'] !== '0' ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold small" for="require_email_verification">Require email verification for new accounts</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?= !empty($settings['enable_notifications']) && $settings['enable_notifications'] !== '0' ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold small" for="enable_notifications">Enable system &amp; portal notifications</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="firebase_enabled" id="firebase_enabled" value="1" <?= !empty($settings['firebase_enabled']) && $settings['firebase_enabled'] !== '0' ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold small" for="firebase_enabled">Enable Cloud Firestore live synchronization</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>