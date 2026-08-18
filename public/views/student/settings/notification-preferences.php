<?php
// Ensure bootstrap is loaded
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

$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

// Handle preference updates
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $userId = current_user()['uid'];
    
    if ($action === 'update_preferences') {
        try {
            $preferences = [
                'emailNotifications' => isset($_POST['emailNotifications']) ? true : false,
                'attendanceAlerts' => isset($_POST['attendanceAlerts']) ? true : false,
                'visitorUpdates' => isset($_POST['visitorUpdates']) ? true : false,
                'incidentAlerts' => isset($_POST['incidentAlerts']) ? true : false,
                'medicalAlerts' => isset($_POST['medicalAlerts']) ? true : false,
                'systemNotifications' => isset($_POST['systemNotifications']) ? true : false,
                'quietHours' => sanitize($_POST['quietHours'] ?? ''),
                'notificationFrequency' => sanitize($_POST['notificationFrequency'] ?? 'immediate'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ];
            
            $firebaseService = FirebaseService::getInstance();
            
            // Try to update existing preferences, or create new ones
            $prefs = $firebaseService->where('notification_preferences', 'userId', '=', $userId);
            
            if (!empty($prefs)) {
                $prefId = $prefs[0]['id'] ?? '';
                $firebaseService->updateDocument('notification_preferences', $prefId, $preferences);
            } else {
                $preferences['userId'] = $userId;
                $firebaseService->addDocument('notification_preferences', $preferences);
            }
            
            flash('success', 'Notification preferences saved successfully');
            redirect(url('views/student/settings/notification-preferences.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to save preferences: ' . $e->getMessage();
        }
    }
}

// Get existing preferences
$firebaseService = FirebaseService::getInstance();
$userId = current_user()['uid'];
$userPrefs = $firebaseService->where('notification_preferences', 'userId', '=', $userId);

$preferences = [
    'emailNotifications' => true,
    'attendanceAlerts' => true,
    'visitorUpdates' => true,
    'incidentAlerts' => true,
    'medicalAlerts' => true,
    'systemNotifications' => true,
    'quietHours' => '',
    'notificationFrequency' => 'immediate',
];

if (!empty($userPrefs)) {
    $preferences = array_merge($preferences, $userPrefs[0]);
}

$pageTitle = 'Notification Preferences';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/settings/notification-preferences.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 700px;">
            <h5 class="mb-3">Notification Preferences</h5>
            <p class="text-muted small mb-4">Customize how and when you receive notifications from the dormitory system.</p>

            <form method="POST">
                <input type="hidden" name="action" value="update_preferences">

                <!-- Notification Types -->
                <div class="mb-4">
                    <h6 class="mb-3">Notification Types</h6>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="emailNotifications" id="emailNotifications"
                               <?= $preferences['emailNotifications'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="emailNotifications">
                            <strong>Email Notifications</strong>
                            <small class="d-block text-muted">Receive updates via email</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="attendanceAlerts" id="attendanceAlerts"
                               <?= $preferences['attendanceAlerts'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="attendanceAlerts">
                            <strong>Attendance Alerts</strong>
                            <small class="d-block text-muted">Get notified about attendance marks and absences</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="visitorUpdates" id="visitorUpdates"
                               <?= $preferences['visitorUpdates'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="visitorUpdates">
                            <strong>Visitor Updates</strong>
                            <small class="d-block text-muted">Get notified when visitor requests are approved/rejected</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="incidentAlerts" id="incidentAlerts"
                               <?= $preferences['incidentAlerts'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="incidentAlerts">
                            <strong>Incident Alerts</strong>
                            <small class="d-block text-muted">Get notified about incidents involving you</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="medicalAlerts" id="medicalAlerts"
                               <?= $preferences['medicalAlerts'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="medicalAlerts">
                            <strong>Medical Alerts</strong>
                            <small class="d-block text-muted">Get notified about health and medical appointments</small>
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="systemNotifications" id="systemNotifications"
                               <?= $preferences['systemNotifications'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="systemNotifications">
                            <strong>System Notifications</strong>
                            <small class="d-block text-muted">General system announcements and updates</small>
                        </label>
                    </div>
                </div>

                <hr>

                <!-- Notification Frequency -->
                <div class="mb-4">
                    <label class="form-label"><strong>Notification Frequency</strong></label>
                    <select name="notificationFrequency" class="form-select">
                        <option value="immediate" <?= $preferences['notificationFrequency'] === 'immediate' ? 'selected' : '' ?>>
                            Immediate - Get notified right away
                        </option>
                        <option value="hourly" <?= $preferences['notificationFrequency'] === 'hourly' ? 'selected' : '' ?>>
                            Hourly - Summary every hour
                        </option>
                        <option value="daily" <?= $preferences['notificationFrequency'] === 'daily' ? 'selected' : '' ?>>
                            Daily - Summary once per day
                        </option>
                    </select>
                    <small class="text-muted d-block mt-1">Choose how often you want to receive notifications</small>
                </div>

                <!-- Quiet Hours -->
                <div class="mb-4">
                    <label class="form-label"><strong>Quiet Hours (Optional)</strong></label>
                    <div class="row g-2">
                        <div class="col-auto">
                            <input type="time" name="quietHours" class="form-control form-control-sm" 
                                   value="<?= e($preferences['quietHours'] ?? '') ?>"
                                   placeholder="e.g., 22:00 - 08:00">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">No notifications will be sent during these hours</small>
                </div>

                <!-- Info Box -->
                <div class="alert alert-info small mb-4">
                    <strong>Note:</strong> Important security and emergency notifications will still be sent during quiet hours.
                </div>

                <!-- Buttons -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Preferences
                    </button>
                    <a href="<?= url('views/student/settings/index.php') ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Preference Summary -->
        <div class="card stat-card p-3 mt-4" style="max-width: 700px;">
            <h6 class="mb-3">Current Settings Summary</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tr>
                        <td><strong>Email Notifications:</strong></td>
                        <td class="text-end"><?= $preferences['emailNotifications'] ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-danger">Disabled</span>' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Notification Frequency:</strong></td>
                        <td class="text-end"><span class="badge bg-info"><?= e(ucfirst($preferences['notificationFrequency'])) ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Quiet Hours:</strong></td>
                        <td class="text-end"><?= !empty($preferences['quietHours']) ? '<span class="badge bg-warning">' . e($preferences['quietHours']) . '</span>' : '<span class="badge bg-secondary">Not set</span>' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Active Alert Types:</strong></td>
                        <td class="text-end">
                            <?php
                            $active = 0;
                            if ($preferences['attendanceAlerts']) $active++;
                            if ($preferences['visitorUpdates']) $active++;
                            if ($preferences['incidentAlerts']) $active++;
                            if ($preferences['medicalAlerts']) $active++;
                            if ($preferences['systemNotifications']) $active++;
                            ?>
                            <span class="badge bg-primary"><?= e($active) ?>/5</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
