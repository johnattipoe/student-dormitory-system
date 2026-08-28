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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$firebase = FirebaseService::getInstance();

$settingsDoc = $firebase->getDocument('user_settings', $userId) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'acuteTriageAlerts' => !empty($_POST['acuteTriageAlerts']),
        'ambulanceDispatchAlerts' => !empty($_POST['ambulanceDispatchAlerts']),
        'dailyClinicReportReminders' => !empty($_POST['dailyClinicReportReminders']),
        'outbreakAdvisories' => !empty($_POST['outbreakAdvisories']),
        'updatedAt' => date(DATE_ATOM),
    ];

    try {
        $firebase->setDocument('user_settings', $userId, $newSettings);
        $settingsDoc = $newSettings;
        flash('success', 'Clinical operational & notification settings updated.');
    } catch (\Throwable $e) {
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }
}

$acuteTriageAlerts = $settingsDoc['acuteTriageAlerts'] ?? true;
$ambulanceDispatchAlerts = $settingsDoc['ambulanceDispatchAlerts'] ?? true;
$dailyClinicReportReminders = $settingsDoc['dailyClinicReportReminders'] ?? true;
$outbreakAdvisories = $settingsDoc['outbreakAdvisories'] ?? true;

$pageTitle = 'Clinic Settings & Preferences';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/nurse/settings/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><i class="bi bi-gear me-2 text-primary"></i> Infirmary & Clinical Settings</h5>
                <p class="text-muted mb-0">Manage health alert triggers, acute triage notifications, and clinic operational preferences.</p>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-heart-pulse me-2 text-primary"></i> Clinical Triage Preferences</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="acuteTriageAlerts" name="acuteTriageAlerts" value="1" <?= $acuteTriageAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="acuteTriageAlerts">Acute & Severe Medical Case Escalations</label>
                    <div class="text-muted small">Receive immediate high-priority alerts when acute cases or injuries are reported by dormitory staff.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="ambulanceDispatchAlerts" name="ambulanceDispatchAlerts" value="1" <?= $ambulanceDispatchAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="ambulanceDispatchAlerts">Hospital Transfer & Ambulance Dispatch Alerts</label>
                    <div class="text-muted small">Receive confirmations and status tracking whenever emergency referrals are dispatched.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="outbreakAdvisories" name="outbreakAdvisories" value="1" <?= $outbreakAdvisories ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="outbreakAdvisories">Campus Health & Outbreak Advisories</label>
                    <div class="text-muted small">Notify clinic staff when institution-wide health advisories are broadcast.</div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="dailyClinicReportReminders" name="dailyClinicReportReminders" value="1" <?= $dailyClinicReportReminders ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="dailyClinicReportReminders">Daily Infirmary Log Reminders</label>
                    <div class="text-muted small">Receive end-of-shift reminders at 6:00 PM to finalize consultation logs.</div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

