<?php
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

use App\Services\FirebaseService;

$pageTitle = 'Advanced Settings';
$csrfToken = $_SESSION['advanced_settings_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['advanced_settings_csrf'] = $csrfToken;
$appConfig = require APP_ROOT . '/app/config/app.php';
$baseDefaults = [
    'default_role' => 'student',
    'registration_requires_approval' => '0',
    'max_login_attempts' => '5',
    'account_lockout_minutes' => '15',
    'check_in_time' => '14:00',
    'check_out_time' => '12:00',
    'curfew_time' => '22:00',
    'visitor_approval_required' => '1',
    'overstay_alert_hours' => '24',
    'maximum_room_occupancy' => '4',
    'late_arrival_minutes' => '15',
    'auto_mark_absent' => '0',
    'weekend_attendance' => '0',
    'email_notifications' => '1',
    'sms_notifications' => '0',
    'notification_recipient_roles' => 'admin,housemaster',
    'reminder_time' => '08:00',
    'maintenance_mode' => '0',
    'institution_logo_url' => '',
    'primary_color' => '#1f6feb',
    'footer_text' => 'Student Dormitory System',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
];

$settings = $baseDefaults;
$settingsDocument = [];
$settingsConnected = false;
$errors = [];

try {
    $settingsDocument = FirebaseService::getInstance()->getDocument(COL_SETTINGS, 'global') ?? [];
    if (!empty($settingsDocument['values']) && is_array($settingsDocument['values'])) {
        $settings = array_merge($settings, $settingsDocument['values']);
        $settingsConnected = true;
    }
} catch (Throwable $e) {
    $errors[] = 'Firebase settings are unavailable. Application defaults are shown.';
}

$checkboxFields = [
    'registration_requires_approval', 'visitor_approval_required', 'auto_mark_absent',
    'weekend_attendance', 'email_notifications', 'sms_notifications', 'maintenance_mode',
];

$normalizeSettings = static function (array $input) use ($baseDefaults, $checkboxFields): array {
    $settings = [];
    foreach ($baseDefaults as $key => $default) {
        if (in_array($key, $checkboxFields, true)) {
            $settings[$key] = !empty($input[$key]) ? '1' : '0';
        } else {
            $settings[$key] = trim((string) ($input[$key] ?? $default));
        }
    }
    return $settings;
};

$validateSettings = static function (array $settings): array {
    $errors = [];
    $integerRules = [
        'max_login_attempts' => [1, 20],
        'account_lockout_minutes' => [1, 1440],
        'overstay_alert_hours' => [1, 720],
        'maximum_room_occupancy' => [1, 100],
        'late_arrival_minutes' => [0, 1440],
    ];
    foreach ($integerRules as $field => [$minimum, $maximum]) {
        $value = filter_var($settings[$field], FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            $errors[$field] = sprintf('Enter a whole number from %d to %d.', $minimum, $maximum);
        }
    }
    if (!in_array($settings['default_role'], ['student', 'staff'], true)) {
        $errors['default_role'] = 'Choose a valid default role.';
    }
    foreach (['check_in_time', 'check_out_time', 'curfew_time', 'reminder_time'] as $field) {
        $time = DateTime::createFromFormat('H:i', $settings[$field]);
        if (!$time || $time->format('H:i') !== $settings[$field]) {
            $errors[$field] = 'Use a valid time in HH:MM format.';
        }
    }
    if ($settings['institution_logo_url'] !== '' && filter_var($settings['institution_logo_url'], FILTER_VALIDATE_URL) === false) {
        $errors['institution_logo_url'] = 'Enter a valid logo URL or leave this field empty.';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $settings['primary_color'])) {
        $errors['primary_color'] = 'Use a six-digit hexadecimal color.';
    }
    if ($settings['email_notifications'] === '1' && !empty($settings['notification_recipient_roles'])) {
        $roles = array_filter(array_map('trim', explode(',', $settings['notification_recipient_roles'])));
        if (!$roles) $errors['notification_recipient_roles'] = 'Enter at least one recipient role.';
    }
    return $errors;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');

    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $errors['csrf'] = 'The form expired. Please refresh the page and try again.';
    }

    if (!$errors && $action === 'export') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="dormitory-settings-' . date('Y-m-d') . '.json"');
        echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!$errors && $action === 'reset') {
        $settings = $baseDefaults;
    } elseif (!$errors && $action === 'import' && isset($_FILES['settings_file']) && $_FILES['settings_file']['error'] === UPLOAD_ERR_OK) {
        $imported = json_decode((string) file_get_contents($_FILES['settings_file']['tmp_name']), true);
        if (!is_array($imported)) {
            $errors['import'] = 'The uploaded file is not valid JSON settings.';
        } else {
            $settings = $normalizeSettings($imported);
        }
    } elseif (!$errors && $action === 'save') {
        $settings = $normalizeSettings($_POST);
    }

    if (!$errors && $action !== 'export') {
        $errors = $validateSettings($settings);
    }

    if (!$errors && $action !== 'export') {
        try {
            $user = current_user();
            $actor = $user['uid'] ?? $user['id'] ?? 'admin';
            $history = $settingsDocument['history'] ?? [];
            if (!is_array($history)) $history = [];
            array_unshift($history, ['updatedBy' => $actor, 'action' => $action, 'updatedAt' => date(DATE_ATOM)]);
            $history = array_slice($history, 0, 10);
            FirebaseService::getInstance()->addDocument(COL_SETTINGS, [
                'values' => $settings,
                'history' => $history,
                'updatedBy' => $actor,
            ], 'global');
            flash('success', $action === 'reset' ? 'Settings reset to defaults.' : 'Advanced settings saved successfully.');
            redirect(url('views/admin/settings/advanced.php'));
        } catch (Throwable $e) {
            $errors['save'] = 'Unable to save settings: ' . $e->getMessage();
        }
    }
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/admin/settings/index.php')],
    ['icon' => 'bi-sliders', 'label' => 'Advanced Settings', 'href' => url('views/admin/settings/advanced.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper admin-settings-page advanced-settings-page">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <span class="admin-kicker">Operations control</span>
                <h1 class="admin-settings-title">Advanced settings</h1>
                <p class="text-muted mb-0">Fine-tune security, attendance, visitor access, notifications, and system appearance.</p>
            </div>
            <div class="d-flex gap-2">
                <form method="POST"><input type="hidden" name="action" value="export"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-download me-1"></i>Export</button></form>
                <button class="btn btn-primary btn-sm" type="submit" form="advanced-settings-form"><i class="bi bi-check2 me-1"></i>Save settings</button>
            </div>
        </div>

        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
        <div class="admin-settings-source alert small d-flex justify-content-between align-items-center"><span><i class="bi bi-info-circle me-1"></i>Settings source: <strong><?= $settingsConnected ? 'Firebase settings/global' : 'Application defaults' ?></strong>.</span><span class="badge bg-<?= $settingsConnected ? 'success' : 'secondary' ?>"><?= $settingsConnected ? 'Connected' : 'Defaults' ?></span></div>

        <form id="advanced-settings-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card stat-card p-3 h-100"><h6>Security & Registration</h6><div class="row g-3 mt-1">
                        <div class="col-md-6"><label class="form-label">Default role</label><select name="default_role" class="form-select"><option value="student" <?= $settings['default_role'] === 'student' ? 'selected' : '' ?>>Student</option><option value="staff" <?= $settings['default_role'] === 'staff' ? 'selected' : '' ?>>Staff</option></select></div>
                        <div class="col-md-6"><label class="form-label">Max login attempts</label><input type="number" name="max_login_attempts" min="1" max="20" class="form-control" value="<?= e($settings['max_login_attempts']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Lockout duration (minutes)</label><input type="number" name="account_lockout_minutes" min="1" max="1440" class="form-control" value="<?= e($settings['account_lockout_minutes']) ?>"></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="registration_requires_approval" value="1" <?= $settings['registration_requires_approval'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Require admin approval for registration</label></div></div>
                    </div></div>
                </div>
                <div class="col-lg-6"><div class="card stat-card p-3 h-100"><h6>Dormitory Operations</h6><div class="row g-3 mt-1">
                    <div class="col-md-6"><label class="form-label">Check-in time</label><input type="time" name="check_in_time" class="form-control" value="<?= e($settings['check_in_time']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Check-out time</label><input type="time" name="check_out_time" class="form-control" value="<?= e($settings['check_out_time']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Curfew time</label><input type="time" name="curfew_time" class="form-control" value="<?= e($settings['curfew_time']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Overstay alert (hours)</label><input type="number" name="overstay_alert_hours" min="1" max="720" class="form-control" value="<?= e($settings['overstay_alert_hours']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Maximum room occupancy</label><input type="number" name="maximum_room_occupancy" min="1" max="100" class="form-control" value="<?= e($settings['maximum_room_occupancy']) ?>"></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="visitor_approval_required" value="1" <?= $settings['visitor_approval_required'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Require visitor approval</label></div></div>
                </div></div></div>
                <div class="col-lg-6"><div class="card stat-card p-3 h-100"><h6>Attendance</h6><div class="row g-3 mt-1">
                    <div class="col-md-6"><label class="form-label">Late arrival threshold (minutes)</label><input type="number" name="late_arrival_minutes" min="0" max="1440" class="form-control" value="<?= e($settings['late_arrival_minutes']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Reminder time</label><input type="time" name="reminder_time" class="form-control" value="<?= e($settings['reminder_time']) ?>"></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_mark_absent" value="1" <?= $settings['auto_mark_absent'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Automatically mark absent students</label></div></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="weekend_attendance" value="1" <?= $settings['weekend_attendance'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Require weekend attendance</label></div></div>
                </div></div></div>
                <div class="col-lg-6"><div class="card stat-card p-3 h-100"><h6>Notifications</h6><div class="row g-3 mt-1">
                    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="email_notifications" value="1" <?= $settings['email_notifications'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Email notifications</label></div></div>
                    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_notifications" value="1" <?= $settings['sms_notifications'] === '1' ? 'checked' : '' ?>><label class="form-check-label">SMS notifications</label></div></div>
                    <div class="col-12"><label class="form-label">Recipient roles</label><input name="notification_recipient_roles" class="form-control" value="<?= e($settings['notification_recipient_roles']) ?>"><small class="text-muted">Comma-separated role names.</small></div>
                </div></div></div>
                <div class="col-lg-6"><div class="card stat-card p-3 h-100"><h6>Maintenance & Appearance</h6><div class="row g-3 mt-1">
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Enable maintenance mode</label></div></div>
                    <div class="col-md-8"><label class="form-label">Institution logo URL</label><input type="url" name="institution_logo_url" class="form-control" value="<?= e($settings['institution_logo_url']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Primary color</label><input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?= e($settings['primary_color']) ?>"></div>
                    <div class="col-12"><label class="form-label">Footer text</label><input name="footer_text" class="form-control" value="<?= e($settings['footer_text']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Date format</label><input name="date_format" class="form-control" value="<?= e($settings['date_format']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Time format</label><input name="time_format" class="form-control" value="<?= e($settings['time_format']) ?>"></div>
                </div></div></div>
                <div class="col-lg-6"><div class="card stat-card p-3 h-100"><h6>Backup & Audit</h6><div class="mb-3"><label class="form-label">Import settings JSON</label><input type="file" name="settings_file" accept="application/json,.json" class="form-control"><small class="text-muted">Imported values are validated before saving.</small></div><div class="d-flex gap-2"><button class="btn btn-outline-primary btn-sm" type="submit" formaction="<?= url('views/admin/settings/advanced.php') ?>" name="action" value="import"><i class="bi bi-upload me-1"></i>Import and save</button><button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="reset" formnovalidate onclick="return confirm('Reset all advanced settings to defaults?')"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset defaults</button></div><hr><h6 class="small">Recent changes</h6><?php $history = $settingsDocument['history'] ?? []; ?><?php if (!$history): ?><p class="text-muted small mb-0">No changes recorded yet.</p><?php else: ?><ul class="list-unstyled small mb-0"><?php foreach (array_slice($history, 0, 5) as $entry): ?><li class="mb-2"><strong><?= e($entry['action'] ?? 'save') ?></strong> by <?= e($entry['updatedBy'] ?? 'admin') ?><br><span class="text-muted"><?= e($entry['updatedAt'] ?? '') ?></span></li><?php endforeach; ?></ul><?php endif; ?></div></div>
            </div>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
