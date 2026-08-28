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
$officerName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Security Officer';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = trim((string) sanitize($_POST['event'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'normal'));
    $details = trim((string) sanitize($_POST['details'] ?? ''));

    if ($event === '') $errors['event'] = 'Event / Log type is required.';
    if ($details === '') $errors['details'] = 'Details and observation notes are required.';

    if (empty($errors)) {
        try {
            $logEntry = [
                'event' => $event,
                'action' => $event,
                'type' => 'security_patrol',
                'details' => $details,
                'description' => $details,
                'priority' => $priority,
                'isManual' => true,
                'userId' => $userId,
                'userName' => $officerName . ' (Security)',
                'performedBy' => $userId,
                'performedByName' => $officerName . ' (Security)',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ];

            FirebaseService::getInstance()->addDocument(COL_ACTIVITY_LOGS, $logEntry);

            flash('success', 'Security observation saved to log.');
            redirect(url('views/security/activity-logs/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to save security log: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Log Security Observation';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/security/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Log Security / Gate Observation</h5>
                <p class="text-muted mb-0">Record gate incidents, vehicle entries, or perimeter patrol observations.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setSecPreset('Perimeter Patrol Check', 'Completed perimeter fence and external gate patrol round. No breaches or irregularities found.', 'normal')">Perimeter Patrol</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setSecPreset('Official Vehicle Entry', 'Delivery / service vehicle entered main gate. Verified driver identity and manifest.', 'normal')">Vehicle Log</button>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="setSecPreset('Gate Curfew Notice', 'Student / visitor approached gate after official curfew hours. Refused unauthorized entry.', 'important')">Curfew Notice</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="event">Event / Operation Type <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['event']) ? 'is-invalid' : '' ?>" id="event" name="event" value="<?= e($_POST['event'] ?? '') ?>" placeholder="e.g. Perimeter Patrol, Vehicle Entry, Curfew Enforcement" required>
                    <?php if (!empty($errors['event'])): ?>
                        <div class="invalid-feedback"><?= e($errors['event']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="normal" <?= ($_POST['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal (Routine)</option>
                        <option value="important" <?= ($_POST['priority'] ?? '') === 'important' ? 'selected' : '' ?>>Important (Requires Note)</option>
                        <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent / Incident</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="details">Observation & Action Details <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['details']) ? 'is-invalid' : '' ?>" id="details" name="details" rows="5" placeholder="Enter complete security observations, officer actions, vehicle plates, or names..." required><?= e($_POST['details'] ?? '') ?></textarea>
                    <?php if (!empty($errors['details'])): ?>
                        <div class="invalid-feedback"><?= e($errors['details']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/security/activity-logs/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check me-1"></i> Save to Security Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setSecPreset(event, details, priority) {
    document.getElementById('event').value = event;
    document.getElementById('details').value = details;
    document.getElementById('priority').value = priority;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

