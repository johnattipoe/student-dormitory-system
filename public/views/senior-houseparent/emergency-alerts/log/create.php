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
use App\Services\StudentService;

$user = current_user() ?? [];
$userId = current_user_id();
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');

$firebase = FirebaseService::getInstance();
$contacts = $firebase->getCollection('emergency_contacts', [], 500);
$students = StudentService::all($houseId);

$selectedContactId = sanitize($_GET['contactId'] ?? '');
$prefillContactName = '';
$prefillContactPhone = '';
if ($selectedContactId !== '') {
    foreach ($contacts as $c) {
        if ((string)($c['id'] ?? '') === $selectedContactId) {
            $prefillContactName = (string)($c['name'] ?? '');
            $prefillContactPhone = (string)($c['phone'] ?? '');
            break;
        }
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactName = trim((string) sanitize($_POST['contactName'] ?? ''));
    $contactPhone = trim((string) sanitize($_POST['contactPhone'] ?? ''));
    $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
    $reason = trim((string) sanitize($_POST['reason'] ?? ''));
    $notes = trim((string) sanitize($_POST['notes'] ?? ''));
    $actionTaken = trim((string) sanitize($_POST['actionTaken'] ?? ''));

    if ($contactName === '') $errors['contactName'] = 'Contact or agency name is required.';
    if ($notes === '') $errors['notes'] = 'Incident call summary and notes are required.';

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

            $incidentData = [
                'contactName' => $contactName,
                'contactPhone' => $contactPhone,
                'reason' => $reason,
                'notes' => $notes,
                'actionTaken' => $actionTaken,
                'studentId' => $studentId ?: null,
                'studentName' => $studentName ?: null,
                'houseId' => $houseId ?: null,
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (Senior Houseparent)',
                'triggeredAt' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ];

            $firebase->addDocument('emergency_incidents', array_filter($incidentData, fn($v) => $v !== null && $v !== ''));

            // Also record to activity logs
            $firebase->addDocument(COL_ACTIVITY_LOGS, [
                'event' => 'Emergency Call Logged',
                'action' => 'Emergency Call: ' . $contactName,
                'type' => 'emergency_call',
                'details' => 'Called ' . $contactName . ' (' . $contactPhone . ') — ' . $notes,
                'houseId' => $houseId,
                'userId' => $userId,
                'userName' => $userName . ' (Senior Houseparent)',
                'performedBy' => $userId,
                'performedByName' => $userName . ' (Senior Houseparent)',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
            ]);

            flash('success', 'Emergency call record logged successfully.');
            redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to log call record: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Log Emergency Call';
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
                <h5 class="mb-1">Log Emergency Call & Incident Response</h5>
                <p class="text-muted mb-0">Record details of phone dispatches to first responders, school clinic, or security.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Emergency Center
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="contactSelect">Quick Select Agency / Contact</label>
                    <select class="form-select" id="contactSelect" onchange="fillContact(this)">
                        <option value="">— Select from Directory or type below —</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?= e($c['phone'] ?? '') ?>" data-name="<?= e($c['name'] ?? '') ?>" <?= $selectedContactId === (string)($c['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($c['name'] ?? 'Contact') ?> (<?= e($c['phone'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="studentId">Involved Student (Optional)</label>
                    <select class="form-select" id="studentId" name="studentId">
                        <option value="">— None / Dormitory-Wide Incident —</option>
                        <?php foreach ($students as $st): ?>
                            <?php $fullName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')); ?>
                            <option value="<?= e($st['id'] ?? '') ?>">
                                <?= e($fullName ?: 'Student') ?> <?= !empty($st['admissionNo']) ? '[' . e($st['admissionNo']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="contactName">Agency / Responder Contacted <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['contactName']) ? 'is-invalid' : '' ?>" id="contactName" name="contactName" value="<?= e($_POST['contactName'] ?? $prefillContactName) ?>" placeholder="e.g. Police Dispatch, School Clinic" required>
                    <?php if (!empty($errors['contactName'])): ?>
                        <div class="invalid-feedback"><?= e($errors['contactName']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="contactPhone">Phone Number Called</label>
                    <input class="form-control" id="contactPhone" name="contactPhone" value="<?= e($_POST['contactPhone'] ?? $prefillContactPhone) ?>" placeholder="e.g. +233 24 000 0000">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="reason">Incident Reason / Emergency Type</label>
                    <input class="form-control" id="reason" name="reason" value="<?= e($_POST['reason'] ?? '') ?>" placeholder="e.g. Medical collapse, power transformer spark, unauthorized intruder">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="notes">Call Summary & Discussion <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['notes']) ? 'is-invalid' : '' ?>" id="notes" name="notes" rows="4" placeholder="Describe the situation reported, responder instructions, and estimated arrival time..." required><?= e($_POST['notes'] ?? '') ?></textarea>
                    <?php if (!empty($errors['notes'])): ?>
                        <div class="invalid-feedback"><?= e($errors['notes']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="actionTaken">Immediate Action Taken on the Ground</label>
                    <textarea class="form-control" id="actionTaken" name="actionTaken" rows="2" placeholder="e.g. Dormitory evacuated, first aid administered, students gathered in assembly area..."><?= e($_POST['actionTaken'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-journal-check me-1"></i> Save Emergency Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillContact(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('contactPhone').value = opt.value;
        document.getElementById('contactName').value = opt.getAttribute('data-name') || '';
    }
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

