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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$firebase = FirebaseService::getInstance();

$settingsDoc = $firebase->getDocument('user_settings', $userId) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'curfewAlerts' => !empty($_POST['curfewAlerts']),
        'rollCallReminders' => !empty($_POST['rollCallReminders']),
        'emergencyBroadcasts' => !empty($_POST['emergencyBroadcasts']),
        'incidentNotifications' => !empty($_POST['incidentNotifications']),
        'visitorCheckinAlerts' => !empty($_POST['visitorCheckinAlerts']),
        'updatedAt' => date(DATE_ATOM),
    ];

    try {
        $firebase->setDocument('user_settings', $userId, $newSettings);
        $settingsDoc = $newSettings;
        flash('success', 'House Master notification & operational settings updated.');
    } catch (\Throwable $e) {
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }
}

$curfewAlerts = $settingsDoc['curfewAlerts'] ?? true;
$rollCallReminders = $settingsDoc['rollCallReminders'] ?? true;
$emergencyBroadcasts = $settingsDoc['emergencyBroadcasts'] ?? true;
$incidentNotifications = $settingsDoc['incidentNotifications'] ?? true;
$visitorCheckinAlerts = $settingsDoc['visitorCheckinAlerts'] ?? false;

$pageTitle = 'House Settings & Preferences';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/house-master/settings/index.php'), 'active' => true],
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
                <h5 class="mb-1"><i class="bi bi-gear me-2 text-primary"></i> House Operational Settings</h5>
                <p class="text-muted mb-0">Configure dormitory notification triggers, curfew alarms, and supervisory preferences.</p>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-bell me-2 text-primary"></i> Alert & Notification Preferences</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="curfewAlerts" name="curfewAlerts" value="1" <?= $curfewAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="curfewAlerts">Curfew & Late Entry Alerts</label>
                    <div class="text-muted small">Receive automated alerts when students in your assigned house check in past curfew.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="rollCallReminders" name="rollCallReminders" value="1" <?= $rollCallReminders ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="rollCallReminders">Daily Roll Call Verification Reminders</label>
                    <div class="text-muted small">Get daily reminders at 9:00 PM to submit dormitory evening roll call.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="emergencyBroadcasts" name="emergencyBroadcasts" value="1" <?= $emergencyBroadcasts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="emergencyBroadcasts">Emergency Dispatch & Evacuation Broadcasts</label>
                    <div class="text-muted small">Receive high-priority emergency sirens and campus evacuation directives.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="incidentNotifications" name="incidentNotifications" value="1" <?= $incidentNotifications ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="incidentNotifications">Disciplinary & Incident Escalations</label>
                    <div class="text-muted small">Notify when high-priority incidents or room damage reports are logged.</div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="visitorCheckinAlerts" name="visitorCheckinAlerts" value="1" <?= $visitorCheckinAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="visitorCheckinAlerts">Visitor Arrivals for House Students</label>
                    <div class="text-muted small">Receive notifications whenever visitors register at the security gate for students in your house.</div>
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

