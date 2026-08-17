<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\MedicalService;

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
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Create Medical Record</h5>
                <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
            </div>
            <form method="POST" action="<?= url('views/nurse/create-record/create-record.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Student ID</label><input type="text" name="studentId" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="normal">Normal</option><option value="moderate">Moderate</option><option value="critical">Critical</option></select></div>
                    <div class="col-md-6"><label class="form-label">Diagnosis</label><input type="text" name="diagnosis" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Treatment</label><input type="text" name="treatment" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"></textarea></div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Record</button>
                    <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
