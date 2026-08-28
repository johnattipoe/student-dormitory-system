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

$students = StudentService::all();
$selectedStudentId = sanitize($_GET['studentId'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new MedicalService())->create([
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
        'treatment' => sanitize($_POST['treatment'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? ''),
        'severity' => sanitize($_POST['severity'] ?? 'normal'),
        'recordedBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
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
                            <label class="form-label" for="student-id">Student</label>
                            <select id="student-id" name="studentId" class="form-select" required>
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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
