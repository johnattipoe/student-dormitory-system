<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\NotificationService;

$user = current_user() ?? [];
$userId = current_user_id();
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $emergencyType = trim((string) sanitize($_POST['emergencyType'] ?? 'fire'));
    $severity = trim((string) sanitize($_POST['severity'] ?? 'critical'));
    $audience = trim((string) sanitize($_POST['audience'] ?? 'all'));

    if ($title === '') $errors['title'] = 'Emergency title is required.';
    if ($message === '') $errors['message'] = 'Emergency instructions / message content is required.';

    if (empty($errors)) {
        try {
            $firebase = FirebaseService::getInstance();

            // 1. Record Emergency Incident
            $incidentId = $firebase->addDocument('emergency_incidents', [
                'title' => $title,
                'notes' => $message,
                'emergencyType' => $emergencyType,
                'severity' => $severity,
                'audience' => $audience,
                'isBroadcast' => true,
                'contactName' => 'CAMPUS EMERGENCY BROADCAST',
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (Senior Houseparent)',
                'triggeredAt' => date(DATE_ATOM),
            ]);

            // 2. Also publish to announcements with high urgency
            $firebase->addDocument('announcements', [
                'title' => 'EMERGENCY ALERT: ' . $title,
                'message' => $message,
                'audience' => $audience === 'house_master' ? 'role' : 'all',
                'targetRole' => $audience === 'house_master' ? ROLE_HOUSE_MASTER : '',
                'type' => 'danger',
                'status' => 'published',
                'isUrgent' => true,
                'createdBy' => $userId,
                'createdByName' => $userName . ' (Senior Houseparent)',
                'publishedAt' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ]);

            // 3. Dispatch real-time high priority push notifications
            $notification = [
                'title' => 'EMERGENCY: ' . $title,
                'message' => $message,
                'type' => 'danger',
                'isUrgent' => true,
                'link' => url('views/senior-houseparent/emergency-alerts/index.php'),
            ];

            $notifyService = new NotificationService();
            if ($audience === 'house_master') {
                $notifyService->broadcastToRole(ROLE_HOUSE_MASTER, $notification);
                $notifyService->broadcastToRole(ROLE_HOUSE_MISTRESS, $notification);
            } else {
                $notifyService->broadcastToAll($notification);
            }

            flash('success', 'Emergency alert broadcasted successfully to target recipients.');
            redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to broadcast emergency alert: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Broadcast Emergency Alert';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/senior-houseparent/emergency-alerts/index.php'), 'active' => true],
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
                <h5 class="mb-1 text-danger"><i class="bi bi-broadcast me-2"></i> Broadcast Emergency Alert</h5>
                <p class="text-muted mb-0">Dispatch immediate high-priority safety advisories and evacuation instructions.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Emergency Center
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4 border-start border-4 border-danger" style="max-width: 850px;">
            <label class="form-label text-danger small text-uppercase fw-bold mb-2"><i class="bi bi-lightning-charge me-1"></i> Quick Emergency Templates</label>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setBroadcastPreset('FIRE EVACUATION ALERT', 'Attention all dormitory residents: Fire alarms have been triggered. Evacuate immediately via the nearest fire exit to the designated assembly field. Do not use elevators or return for personal belongings.', 'fire', 'critical', 'all')">Fire Evacuation</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setBroadcastPreset('SEVERE WEATHER WARNING', 'Severe thunderstorm / flood warning in effect. All students must remain indoors inside assigned dormitory common rooms. Keep away from windows and power installations.', 'weather', 'critical', 'all')">Severe Storm Warning</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setBroadcastPreset('CAMPUS SECURITY LOCKDOWN', 'Campus security lockdown activated. All dormitory gates are locked. Students must stay in their rooms until security all-clear is confirmed.', 'security', 'critical', 'all')">Security Lockdown</button>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="setBroadcastPreset('POWER & UTILITY OUTAGE NOTICE', 'Emergency power generator activated due to main grid outage. Emergency lighting is active in hallways. Conserve water and maintain order.', 'power', 'high', 'all')">Power Outage Notice</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="title">Alert Title <span class="text-danger">*</span></label>
                    <input class="form-control form-control-lg <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. FIRE EVACUATION ALERT" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="emergencyType">Emergency Category</label>
                    <select class="form-select form-select-lg" id="emergencyType" name="emergencyType">
                        <option value="fire">Fire & Evacuation</option>
                        <option value="medical">Medical Emergency</option>
                        <option value="security">Security & Lockdown</option>
                        <option value="weather">Severe Weather</option>
                        <option value="power">Utility / Power Outage</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="audience">Broadcast Audience</label>
                    <select class="form-select" id="audience" name="audience">
                        <option value="all">Entire Dormitory (All Students, House Masters, Staff)</option>
                        <option value="house_master">House Masters & Mistresses Only</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="severity">Severity Level</label>
                    <select class="form-select" id="severity" name="severity">
                        <option value="critical">Critical (Immediate Evacuation / Lockdown)</option>
                        <option value="high">High Alert (Urgent Safety Notice)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="message">Emergency Instructions & Protocol Details <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" placeholder="Enter clear, concise instructions for students and staff..." required><?= e($_POST['message'] ?? '') ?></textarea>
                    <?php if (!empty($errors['message'])): ?>
                        <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-danger btn-lg px-4" onclick="return confirm('Are you sure you want to broadcast this urgent emergency alert across the dormitory system?')">
                        <i class="bi bi-broadcast me-2"></i> Trigger Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setBroadcastPreset(title, message, type, severity, audience) {
    document.getElementById('title').value = title;
    document.getElementById('message').value = message;
    document.getElementById('emergencyType').value = type;
    document.getElementById('severity').value = severity;
    document.getElementById('audience').value = audience;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

