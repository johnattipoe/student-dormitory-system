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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\MedicalService;

$medicalService = new MedicalService();
$recordId = sanitize($_GET['id'] ?? '');
$record = $medicalService->find($recordId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($recordId === '') {
        flash('error', 'Medical record ID is required.');
        redirect(base_url('index.php?route=/views/nurse/medical-records/medical-records.php'));
    }

    $result = $medicalService->update($recordId, [
        'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
        'treatment' => sanitize($_POST['treatment'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? ''),
        'severity' => sanitize($_POST['severity'] ?? 'normal'),
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/nurse/medical-records/medical-records.php'));
}

$pageTitle = 'Edit Medical Record';
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
                <h5 class="mb-0">Edit Medical Record</h5>
                <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
            </div>
            <?php if (!$record): ?>
                <div class="alert alert-warning">Medical record not found.</div>
            <?php else: ?>
            <form method="POST" action="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode($recordId)) ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Diagnosis</label><input type="text" name="diagnosis" class="form-control" value="<?= e($record['diagnosis'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Treatment</label><input type="text" name="treatment" class="form-control" value="<?= e($record['treatment'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="normal" <?= (($record['severity'] ?? '') === 'normal') ? 'selected' : '' ?>>Normal</option><option value="moderate" <?= (($record['severity'] ?? '') === 'moderate') ? 'selected' : '' ?>>Moderate</option><option value="critical" <?= (($record['severity'] ?? '') === 'critical') ? 'selected' : '' ?>>Critical</option></select></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"><?= e($record['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
