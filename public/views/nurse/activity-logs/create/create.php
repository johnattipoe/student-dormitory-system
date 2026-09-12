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
use App\Services\StudentService;

$user = current_user() ?? [];
$userId = current_user_id();
$nurseName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Staff Nurse';

$students = StudentService::all();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = trim((string) sanitize($_POST['event'] ?? ''));
    $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
    $severity = trim((string) sanitize($_POST['severity'] ?? 'normal'));
    $details = trim((string) sanitize($_POST['details'] ?? ''));

    if ($event === '') $errors['event'] = 'Event / Clinical action is required.';
    if ($details === '') $errors['details'] = 'Clinical notes and details are required.';

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

            $logEntry = [
                'event' => $event,
                'action' => $event,
                'type' => 'clinical_supervision',
                'details' => $details,
                'description' => $details,
                'severity' => $severity,
                'priority' => $severity,
                'isManual' => true,
                'studentId' => $studentId ?: null,
                'studentName' => $studentName ?: null,
                'userId' => $userId,
                'userName' => $nurseName . ' (Nurse)',
                'performedBy' => $userId,
                'performedByName' => $nurseName . ' (Nurse)',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ];

            FirebaseService::getInstance()->addDocument(COL_ACTIVITY_LOGS, array_filter($logEntry, fn($v) => $v !== null));

            flash('success', 'Clinical observation logged successfully.');
            redirect(url('views/nurse/activity-logs/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to record clinical log: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Log Clinical Note';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Audit Trail', 'href' => url('views/nurse/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Log Clinical Observation / Triage Note</h5>
                <p class="text-muted mb-0">Record a patient consultation observation or medical follow-up note.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Audit Trail
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setNursePreset('Routine Health Screening', 'Completed routine blood pressure and temperature screening for dormitory students.', 'normal')">Routine Screening</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setNursePreset('First Aid & Wound Dressing', 'Administered antiseptic cleaning and sterile dressing for minor sports scrape.', 'normal')">Wound Dressing</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setNursePreset('Hospital Referral / Emergency', 'Patient evaluated with acute symptoms. Prepared referral note for district hospital transfer.', 'emergency')">Emergency Referral</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="event">Event / Procedure <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['event']) ? 'is-invalid' : '' ?>" id="event" name="event" value="<?= e($_POST['event'] ?? '') ?>" placeholder="e.g. Health Screening, First Aid, Referral" required>
                    <?php if (!empty($errors['event'])): ?>
                        <div class="invalid-feedback"><?= e($errors['event']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="severity">Severity / Priority</label>
                    <select class="form-select" id="severity" name="severity">
                        <option value="normal" <?= ($_POST['severity'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal (Routine)</option>
                        <option value="high" <?= ($_POST['severity'] ?? '') === 'high' ? 'selected' : '' ?>>High (Requires Monitoring)</option>
                        <option value="emergency" <?= ($_POST['severity'] ?? '') === 'emergency' ? 'selected' : '' ?>>Emergency / Critical</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="studentId">Student Patient (Optional)</label>
                    <select class="form-select" id="studentId" name="studentId">
                        <option value="">— General Clinic Event / Multiple Students —</option>
                        <?php foreach ($students as $st): ?>
                            <?php $fullName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')); ?>
                            <option value="<?= e($st['id'] ?? '') ?>" <?= ($_POST['studentId'] ?? '') === ($st['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($fullName ?: 'Student') ?> <?= !empty($st['admissionNo']) ? '[' . e($st['admissionNo']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="details">Clinical Details & Treatment Notes <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['details']) ? 'is-invalid' : '' ?>" id="details" name="details" rows="5" placeholder="Enter full consultation notes, vital signs, administered medications..." required><?= e($_POST['details'] ?? '') ?></textarea>
                    <?php if (!empty($errors['details'])): ?>
                        <div class="invalid-feedback"><?= e($errors['details']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/nurse/activity-logs/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save to Clinical Audit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setNursePreset(event, details, severity) {
    document.getElementById('event').value = event;
    document.getElementById('details').value = details;
    document.getElementById('severity').value = severity;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

