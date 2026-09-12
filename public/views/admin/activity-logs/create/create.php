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

$user = current_user() ?? [];
$userId = current_user_id() ?: 'default-admin';
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Administrator';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = trim((string) sanitize($_POST['event'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'normal'));
    $details = trim((string) sanitize($_POST['details'] ?? ''));
    $targetRole = trim((string) sanitize($_POST['targetRole'] ?? 'all'));

    if ($event === '') {
        $errors['event'] = 'Event / Directive type is required.';
    }
    if ($details === '') {
        $errors['details'] = 'Details and observation notes are required.';
    }

    if (empty($errors)) {
        try {
            $logEntry = [
                'event' => $event,
                'action' => $event,
                'type' => 'admin_directive',
                'details' => $details,
                'description' => $details,
                'priority' => $priority,
                'targetRole' => $targetRole,
                'isManual' => true,
                'userId' => $userId,
                'userName' => $userName . ' (Administrator)',
                'performedBy' => $userId,
                'performedByName' => $userName . ' (Administrator)',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ];

            FirebaseService::getInstance()->addDocument(COL_ACTIVITY_LOGS, $logEntry);

            flash('success', 'Administrative audit record saved.');
            redirect(url('views/admin/activity-logs/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to save audit record: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Record System Audit Note';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-list-check', 'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Record Administrative Audit Log</h5>
                <p class="text-muted mb-0">Append an administrative directive, system note, or compliance audit record.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAdminPreset('System Configuration Audit', 'Completed routine database backup and verified permission matrix across user roles.', 'normal')">System Audit</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAdminPreset('Term Dormitory Allocation Directives', 'Published official room and bed allocation quotas for incoming term.', 'normal')">Allocation Directives</button>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAdminPreset('Security Protocol Enforcement', 'Reviewed gate access logs and updated visitor curfew thresholds.', 'important')">Security Review</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="event">Directive / Action Type <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['event']) ? 'is-invalid' : '' ?>" id="event" name="event" value="<?= e($_POST['event'] ?? '') ?>" placeholder="e.g. System Configuration Audit, Security Directives" required>
                    <?php if (!empty($errors['event'])): ?>
                        <div class="invalid-feedback"><?= e($errors['event']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="normal" <?= ($_POST['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="important" <?= ($_POST['priority'] ?? '') === 'important' ? 'selected' : '' ?>>Important</option>
                        <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent / Critical</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="details">Details & Notes <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['details']) ? 'is-invalid' : '' ?>" id="details" name="details" rows="6" placeholder="Enter full administrative notes, findings, or directives..." required><?= e($_POST['details'] ?? '') ?></textarea>
                    <?php if (!empty($errors['details'])): ?>
                        <div class="invalid-feedback"><?= e($errors['details']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/admin/activity-logs/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save to Audit Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setAdminPreset(event, details, priority) {
    document.getElementById('event').value = event;
    document.getElementById('details').value = details;
    document.getElementById('priority').value = priority;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

