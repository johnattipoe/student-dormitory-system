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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\FirebaseService;

$user = current_user();
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save preferences
    $notifyIncidents = !empty($_POST['notifyIncidents']);
    $notifyMedical = !empty($_POST['notifyMedical']);
    $notifyAttendance = !empty($_POST['notifyAttendance']);
    $notifyVisitors = !empty($_POST['notifyVisitors']);

    try {
        FirebaseService::getInstance()->updateDocument('users', current_user_id(), [
            'settings' => [
                'notifyIncidents' => $notifyIncidents,
                'notifyMedical' => $notifyMedical,
                'notifyAttendance' => $notifyAttendance,
                'notifyVisitors' => $notifyVisitors,
                'updatedAt' => date(DATE_ATOM),
            ]
        ]);
        flash('success', 'Preferences and settings saved successfully.');
    } catch (\Throwable $e) {
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }
    redirect(url('views/senior-houseparent/settings/index.php'));
}

$userDoc = FirebaseService::getInstance()->getDocument('users', current_user_id());
$savedSettings = $userDoc['settings'] ?? [];

$pageTitle = 'Settings';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/senior-houseparent/settings/index.php'), 'active' => true],
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
                <h5 class="mb-1">Dormitory & Account Settings</h5>
                <p class="text-muted mb-0">Manage notification preferences, dormitory operational options, and account security.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border px-3 py-2"><?= e($houseName) ?></span>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-bell me-2 text-primary"></i> Notification & Alert Preferences</h6>
                    <form method="POST">
                        <div class="list-group list-group-flush mb-4">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <div class="fw-semibold">Emergency & Medical Alerts</div>
                                    <small class="text-muted">Receive instant priority push notifications when a student in your house visits the clinic with an emergency condition.</small>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notifyMedical" value="1" <?= !isset($savedSettings['notifyMedical']) || !empty($savedSettings['notifyMedical']) ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <div class="fw-semibold">High-Priority Incident Alerts</div>
                                    <small class="text-muted">Receive notifications whenever a severe discipline or safety incident is logged.</small>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notifyIncidents" value="1" <?= !isset($savedSettings['notifyIncidents']) || !empty($savedSettings['notifyIncidents']) ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <div class="fw-semibold">Daily Attendance & Roll Call Reminders</div>
                                    <small class="text-muted">Daily evening prompts for dorm roll call completion and absentee summaries.</small>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notifyAttendance" value="1" <?= !isset($savedSettings['notifyAttendance']) || !empty($savedSettings['notifyAttendance']) ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <div class="fw-semibold">Visitor Arrival & Check-In Notifications</div>
                                    <small class="text-muted">Receive notifications when parents or guardians check in at the gate to visit a student.</small>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notifyVisitors" value="1" <?= !isset($savedSettings['notifyVisitors']) || !empty($savedSettings['notifyVisitors']) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card stat-card p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i> Account Security</h6>
                    <p class="text-muted small mb-3">Manage your login credentials, personal contact phone, and profile details.</p>
                    <a class="btn btn-outline-primary btn-sm w-100" href="<?= url('views/senior-houseparent/profile.php') ?>">
                        <i class="bi bi-person-circle me-1"></i> Manage Profile & Password
                    </a>
                </div>

                <div class="card stat-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-telephone-inbound me-2 text-danger"></i> Emergency Protocols</h6>
                    <p class="text-muted small mb-3">Access emergency services directory and broadcast urgent dormitory advisories.</p>
                    <a class="btn btn-outline-danger btn-sm w-100" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">
                        <i class="bi bi-exclamation-triangle me-1"></i> Emergency Contacts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

