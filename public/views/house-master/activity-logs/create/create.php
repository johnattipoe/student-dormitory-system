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
use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\RoomService;

$roleTitle = current_role() === ROLE_HOUSE_MISTRESS ? 'House Mistress' : 'House Master';
$user = current_user() ?? [];
$userId = current_user_id();
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: $roleTitle;
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$rooms = RoomService::all($houseId);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = trim((string) sanitize($_POST['event'] ?? ''));
    $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
    $roomId = trim((string) sanitize($_POST['roomId'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'normal'));
    $details = trim((string) sanitize($_POST['details'] ?? ''));

    if ($event === '') {
        $errors['event'] = 'Please select or specify an event type.';
    }
    if ($details === '') {
        $errors['details'] = 'Log details and observations are required.';
    }

    if (empty($errors)) {
        try {
            $studentName = '';
            if ($studentId !== '') {
                foreach ($students as $st) {
                    if ((string)($st['id'] ?? '') === $studentId) {
                        $studentName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''));
                        break;
                    }
                }
            }

            $roomNumber = '';
            if ($roomId !== '') {
                foreach ($rooms as $rm) {
                    if ((string)($rm['id'] ?? '') === $roomId) {
                        $roomNumber = (string)($rm['roomNumber'] ?? $rm['name'] ?? '');
                        break;
                    }
                }
            }

            $logEntry = [
                'event' => $event,
                'action' => $event,
                'type' => 'house_supervision',
                'details' => $details,
                'description' => $details,
                'priority' => $priority,
                'isManual' => true,
                'houseId' => $houseId,
                'houseName' => $houseName,
                'userId' => $userId,
                'userName' => $userName . ' (' . $roleTitle . ')',
                'performedBy' => $userId,
                'performedByName' => $userName . ' (' . $roleTitle . ')',
                'studentId' => $studentId ?: null,
                'studentName' => $studentName ?: null,
                'roomId' => $roomId ?: null,
                'roomNumber' => $roomNumber ?: null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ];

            FirebaseService::getInstance()->addDocument(COL_ACTIVITY_LOGS, array_filter($logEntry, fn($v) => $v !== null));

            flash('success', 'Observation logged to house audit trail.');
            redirect(url('views/house-master/activity-logs/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to record activity log: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Record Activity Log';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/house-master/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Record House Activity & Supervisory Note</h5>
                <p class="text-muted mb-0">Record an observation, room check, or lights out note for <?= e($houseName) ?>.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <label class="form-label text-muted small text-uppercase fw-bold mb-2">Observation Presets</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('Morning Dormitory Check', 'Conducted morning inspection round. All rooms tidy and students departed for morning assembly.', 'normal')">Morning Check</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('Evening Lights Out Verification', 'Verified lights out at 10:00 PM. Roll call verified against room capacity.', 'normal')">Lights Out Round</button>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="setPreset('Maintenance Issue Observed', 'Observed plumbing/electrical repair requirement in dormitory block. Reported to facilities.', 'important')">Maintenance Observation</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="event">Action / Event Type <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['event']) ? 'is-invalid' : '' ?>" id="event" name="event" value="<?= e($_POST['event'] ?? '') ?>" placeholder="e.g. Morning Dorm Check, Curfew Round" required>
                    <?php if (!empty($errors['event'])): ?>
                        <div class="invalid-feedback"><?= e($errors['event']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="normal" <?= ($_POST['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="important" <?= ($_POST['priority'] ?? '') === 'important' ? 'selected' : '' ?>>Important</option>
                        <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="studentId">Involved Student (Optional)</label>
                    <select class="form-select" id="studentId" name="studentId">
                        <option value="">— None / General House Event —</option>
                        <?php foreach ($students as $st): ?>
                            <?php $fullName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')); ?>
                            <option value="<?= e($st['id'] ?? '') ?>" <?= ($_POST['studentId'] ?? '') === ($st['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($fullName ?: 'Student') ?> <?= !empty($st['admissionNo']) ? '[' . e($st['admissionNo']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="roomId">Involved Room (Optional)</label>
                    <select class="form-select" id="roomId" name="roomId">
                        <option value="">— None / Entire House —</option>
                        <?php foreach ($rooms as $rm): ?>
                            <option value="<?= e($rm['id'] ?? '') ?>" <?= ($_POST['roomId'] ?? '') === ($rm['id'] ?? '') ? 'selected' : '' ?>>
                                Room <?= e($rm['roomNumber'] ?? $rm['name'] ?? 'Room') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="details">Observation Details <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['details']) ? 'is-invalid' : '' ?>" id="details" name="details" rows="5" placeholder="Enter full details of the supervisory observation or action..." required><?= e($_POST['details'] ?? '') ?></textarea>
                    <?php if (!empty($errors['details'])): ?>
                        <div class="invalid-feedback"><?= e($errors['details']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/activity-logs/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Observation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setPreset(event, details, priority) {
    document.getElementById('event').value = event;
    document.getElementById('details').value = details;
    document.getElementById('priority').value = priority;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

