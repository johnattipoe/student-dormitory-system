<?php
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

use App\Services\MedicalService;
use App\Services\StudentService;
use App\Services\BmsSmsService;

$students = StudentService::all();
$studentDataMap = [];
foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') {
        continue;
    }
    $studentDataMap[$studentId] = [
        'guardianName' => trim((string) ($student['guardianName'] ?? '')),
        'guardianPhone' => trim((string) ($student['guardianPhone'] ?? '')),
    ];
}
$selectedStudentId = sanitize($_GET['studentId'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = sanitize($_POST['studentId'] ?? '');
    $guardianPhone = sanitize($_POST['guardianPhone'] ?? '');
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $treatment = sanitize($_POST['treatment'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $severity = sanitize($_POST['severity'] ?? 'normal');
    
    $result = (new MedicalService())->create([
        'studentId' => $studentId,
        'diagnosis' => $diagnosis,
        'treatment' => $treatment,
        'notes' => $notes,
        'severity' => $severity,
        'recordedBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
    
    // Send SMS notification for medical record creation
    if ($result['success']) {
        try {
            $student = StudentService::find($studentId) ?? [];
            // Use provided phone number or fall back to student's phone
            $phoneToUse = !empty($guardianPhone) ? $guardianPhone : (string) ($student['guardianPhone'] ?? '');
            $appConfig = app_config();
            $smsEnabled = (string) ($appConfig['advanced']['sms_notifications'] ?? '0');
            
            // Log to file
            $logFile = APP_ROOT . '/storage/logs/sms-medical-' . date('Y-m-d') . '.log';
            $logMsg = "[" . date('H:i:s') . "] [CREATE] SMS enabled: {$smsEnabled}, Guardian phone: " . (!empty($phoneToUse) ? substr($phoneToUse, -4, 4) : 'EMPTY') . "\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
            
            if ($phoneToUse !== '' && $smsEnabled === '1') {
                $smsService = new BmsSmsService();
                $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
                $currentUser = current_user() ?? [];
                $nurseName = trim((string) (($currentUser['fullName'] ?? $currentUser['name'] ?? $currentUser['firstName'] ?? '') . ' ' . ($currentUser['lastName'] ?? '')));
                $nurseName = $nurseName !== '' ? $nurseName : 'Nurse';
                $notesText = trim($notes);

                $smsMessage = "Your ward {$studentName} was attended to by {$nurseName}. Diagnosis: {$diagnosis}. Treatment: {$treatment}. Notes: {$notesText}. Severity: {$severity}.";

                if (mb_strlen($smsMessage) > 160) {
                    $shortNotes = $notesText !== '' ? substr($notesText, 0, 40) : 'see record';
                    $smsMessage = "Your ward {$studentName} was attended to by {$nurseName}. Diagnosis: {$diagnosis}. Notes: {$shortNotes}. Severity: {$severity}.";
                }
                
                $logMsg = "[" . date('H:i:s') . "] [CREATE] Sending SMS to {$phoneToUse}, length: " . mb_strlen($smsMessage) . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                
                $smsResult = $smsService->send($phoneToUse, $smsMessage);
                
                if ($smsResult['success']) {
                    $logMsg = "[" . date('H:i:s') . "] [CREATE] ✓ SMS SENT to {$phoneToUse}\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    flash('info', 'Medical record created successfully. Parent/guardian has been notified via SMS.');
                } else {
                    $logMsg = "[" . date('H:i:s') . "] [CREATE] ✗ SMS FAILED: " . ($smsResult['message'] ?? 'Unknown error') . " | Response: " . json_encode($smsResult['provider_response'] ?? []) . "\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    flash('warning', 'Medical record created successfully, but SMS notification could not be sent: ' . ($smsResult['message'] ?? 'Unknown error'));
                }
            } elseif ($phoneToUse === '' && $smsEnabled === '1') {
                $logMsg = "[" . date('H:i:s') . "] [CREATE] ⚠ No guardian phone for student {$studentId}\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                flash('info', 'Medical record created successfully. No guardian phone number was found to send SMS notification.');
            }
        } catch (Throwable $e) {
            $logFile = APP_ROOT . '/storage/logs/sms-medical-' . date('Y-m-d') . '.log';
            $logMsg = "[" . date('H:i:s') . "] [CREATE] ✗ ERROR: " . $e->getMessage() . "\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
        }
    }
    
    redirect(base_url('index.php?route=/views/nurse/medical-records/medical-records.php'));
}

$pageTitle = 'Create Medical Record';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php'), 'active' => true],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content nurse-portal">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-clipboard2-pulse"></i></div>
            <div>
                <span class="nurse-kicker">Clinical entry</span>
                <h1>Create medical record</h1>
                <p>Record a diagnosis, treatment, severity, and nurse notes for a student visit.</p>
            </div>
            <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-light">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="nurse-card-panel">
                    <div class="nurse-card-header">
                        <div>
                            <span class="nurse-kicker">Record form</span>
                            <h2>Health details</h2>
                            <p>Select the student first, then enter the clinical details.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= url('views/nurse/create-record/create-record.php') ?>" class="row g-3 nurse-profile-form">
                        <div class="col-md-6">
                            <label class="form-label" for="studentSearchInput">Student</label>
                            <div class="input-group">
                                <input type="text" id="studentSearchInput" class="form-control" placeholder="Search student by name or admission no" aria-label="Search student">
                                <button type="button" class="btn btn-outline-secondary" onclick="filterStudentSelect()">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>

                            <br>
                            
                            <select id="student-id" name="studentId" class="form-select mt-2" required onchange="updateStudentDetails()">
                                <option value="">Select student</option>
                                <?php foreach ($students as $student): ?>
                                    <?php $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')); ?>
                                    <?php $optionStudentId = (string) ($student['id'] ?? ''); ?>
                                    <option value="<?= e($optionStudentId) ?>" <?= $selectedStudentId === $optionStudentId ? 'selected' : '' ?>>
                                        <?= e($studentName !== '' ? $studentName : 'Unnamed student') ?>
                                        (<?= e($student['admissionNo'] ?? $student['studentId'] ?? $student['id'] ?? 'No ID') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="moderate">Moderate</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent/Guardian Name</label>
                            <input type="text" name="guardianName" id="guardianName" class="form-control" readonly placeholder="Selected student's guardian name">
                            <small class="text-muted">Auto-filled from student record. Click to edit if needed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent/Guardian Phone</label>
                            <input type="tel" name="guardianPhone" id="guardianPhone" class="form-control" readonly placeholder="e.g., 0598751009" title="Leave empty to use student's registered phone">
                            <small class="text-muted">Auto-filled from student record. Click to edit if needed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Treatment</label>
                            <input type="text" name="treatment" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="5" placeholder="Follow-up instructions, observations, medication notes, or referrals"></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Save record
                            </button>
                            <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="nurse-side-card">
                    <h2>Entry checklist</h2>
                    <div class="nurse-info-list">
                        <div><i class="bi bi-person-check"></i><span>Student</span><strong>Required</strong></div>
                        <div><i class="bi bi-heart-pulse"></i><span>Severity</span><strong>Normal / Moderate / Critical</strong></div>
                        <div><i class="bi bi-journal-text"></i><span>Notes</span><strong>Add follow-up guidance</strong></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<script>
const studentData = <?php echo json_encode($studentDataMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function filterStudentSelect() {
    const searchInput = document.getElementById('studentSearchInput');
    const studentSelect = document.getElementById('student-id');

    if (!searchInput || !studentSelect) {
        return;
    }

    const query = searchInput.value.trim().toLowerCase();
    const options = Array.from(studentSelect.options);

    options.forEach((option) => {
        if (!option.value) {
            option.hidden = false;
            return;
        }

        const text = option.text.toLowerCase();
        option.hidden = query !== '' && !text.includes(query);
    });

    const visibleOptions = options.filter((option) => option.value && !option.hidden);
    if (query !== '' && visibleOptions.length > 0) {
        const firstMatch = visibleOptions[0];
        studentSelect.value = firstMatch.value;
        updateStudentDetails();
    }
}

function updateStudentDetails() {
    const studentSelect = document.getElementById('student-id');
    const guardianNameInput = document.getElementById('guardianName');
    const guardianPhoneInput = document.getElementById('guardianPhone');

    if (!studentSelect || !guardianNameInput || !guardianPhoneInput) {
        return;
    }

    const studentId = studentSelect.value;
    const student = studentData[studentId] || null;
    const guardianName = student && student.guardianName ? student.guardianName : '';
    const guardianPhone = student && student.guardianPhone ? student.guardianPhone : '';

    guardianNameInput.value = guardianName;
    guardianPhoneInput.value = guardianPhone;
    guardianNameInput.readOnly = false;
    guardianPhoneInput.readOnly = false;
}

function makeGuardianFieldsReadOnly() {
    const guardianNameInput = document.getElementById('guardianName');
    const guardianPhoneInput = document.getElementById('guardianPhone');

    if (guardianNameInput) guardianNameInput.readOnly = true;
    if (guardianPhoneInput) guardianPhoneInput.readOnly = true;
}


window.addEventListener('DOMContentLoaded', function () {
    const guardianNameInput = document.getElementById('guardianName');
    const guardianPhoneInput = document.getElementById('guardianPhone');

    if (guardianNameInput) {
        guardianNameInput.addEventListener('focus', function () {
            if (this.readOnly) {
                this.readOnly = false;
            }
        });
    }

    if (guardianPhoneInput) {
        guardianPhoneInput.addEventListener('focus', function () {
            if (this.readOnly) {
                this.readOnly = false;
            }
        });
    }

    updateStudentDetails();
    makeGuardianFieldsReadOnly();

    const searchInput = document.getElementById('studentSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterStudentSelect);
    }
});
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
