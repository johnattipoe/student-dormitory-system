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

use App\Services\EmergencyReferralService;
use App\Services\StudentService;

$pageTitle = 'Create Referral';
$referralService = new EmergencyReferralService();
$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') continue;

    $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $students[$studentId] = $name !== '' ? $name : 'Unnamed student';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = sanitize($_POST['studentId'] ?? '');
    $facility = sanitize($_POST['facility'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');

    $result = $referralService->create([
        'studentId' => $studentId,
        'facility' => $facility,
        'reason' => $reason,
        'doctor' => sanitize($_POST['doctor'] ?? ''),
        'urgency' => sanitize($_POST['urgency'] ?? 'urgent'),
        'notes' => sanitize($_POST['notes'] ?? ''),
        'createdBy' => current_user_id(),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);

    redirect(url('views/nurse/emergency-cases/referral/create.php'));
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-medical-fill text-info me-2"></i>Create Referral</h4>
                <p class="text-muted mb-0">Send a clinical transfer or follow-up referral for review.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/nurse/emergency-cases/referral/view.php') ?>">
                    <i class="bi bi-eye me-1"></i>View Referrals
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard2-pulse me-2 text-info"></i>Referral details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student</label>
                            <select name="studentId" class="form-select" required>
                                <option value="">Select student</option>
                                <?php foreach ($students as $studentId => $name): ?>
                                    <option value="<?= e((string) $studentId) ?>"><?= e($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referral facility</label>
                            <input type="text" name="facility" class="form-control" placeholder="Regional Hospital / Clinic" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Attending doctor</label>
                            <input type="text" name="doctor" class="form-control" placeholder="Dr. Asare">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Urgency</label>
                            <select name="urgency" class="form-select">
                                <option value="urgent">Urgent</option>
                                <option value="routine">Routine</option>
                                <option value="follow-up">Follow-up</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Reason for referral</label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="Describe the reason for referral" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Observation summary and treatment notes"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-info text-white">
                            <i class="bi bi-send me-1"></i>Save Referral
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
