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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$firebase = FirebaseService::getInstance();

$settingsDoc = $firebase->getDocument('user_settings', $userId) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'curfewBreachSiren' => !empty($_POST['curfewBreachSiren']),
        'visitorOverstayAlerts' => !empty($_POST['visitorOverstayAlerts']),
        'lockdownAlerts' => !empty($_POST['lockdownAlerts']),
        'patrolLogReminders' => !empty($_POST['patrolLogReminders']),
        'updatedAt' => date(DATE_ATOM),
    ];

    try {
        $firebase->setDocument('user_settings', $userId, $newSettings);
        $settingsDoc = $newSettings;
        flash('success', 'Security gate & operational settings updated.');
    } catch (\Throwable $e) {
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }
}

$curfewBreachSiren = $settingsDoc['curfewBreachSiren'] ?? true;
$visitorOverstayAlerts = $settingsDoc['visitorOverstayAlerts'] ?? true;
$lockdownAlerts = $settingsDoc['lockdownAlerts'] ?? true;
$patrolLogReminders = $settingsDoc['patrolLogReminders'] ?? true;

$pageTitle = 'Security Gate Settings';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/security/settings/index.php'), 'active' => true],
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
                <h5 class="mb-1"><i class="bi bi-gear me-2 text-primary"></i> Gate Post & Security Settings</h5>
                <p class="text-muted mb-0">Configure perimeter alarm triggers, visitor overstay thresholds, and guard shift preferences.</p>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-shield-check me-2 text-primary"></i> Gate Alert Triggers</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="curfewBreachSiren" name="curfewBreachSiren" value="1" <?= $curfewBreachSiren ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="curfewBreachSiren">Curfew Lockdown & Gate Breach Siren</label>
                    <div class="text-muted small">Trigger audio/visual alerts on dashboard when entries are attempted during active curfew.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="visitorOverstayAlerts" name="visitorOverstayAlerts" value="1" <?= $visitorOverstayAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="visitorOverstayAlerts">Visitor Overstay Escalation Alerts</label>
                    <div class="text-muted small">Notify gate officers when visitors remain on campus past their approved departure time.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="lockdownAlerts" name="lockdownAlerts" value="1" <?= $lockdownAlerts ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="lockdownAlerts">Campus-Wide Lockdown & Emergency Directives</label>
                    <div class="text-muted small">Receive immediate emergency broadcast alerts from school administration.</div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="patrolLogReminders" name="patrolLogReminders" value="1" <?= $patrolLogReminders ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="patrolLogReminders">Shift Perimeter Patrol Reminders</label>
                    <div class="text-muted small">Remind duty officers to log routine hourly perimeter walk checks.</div>
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

