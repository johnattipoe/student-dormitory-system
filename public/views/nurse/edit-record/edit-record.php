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
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php'), 'active' => true],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content nurse-portal">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-pencil-square"></i></div>
            <div>
                <span class="nurse-kicker">Clinical update</span>
                <h1>Edit medical record</h1>
                <p>Update treatment, notes, and severity when a student case changes.</p>
            </div>
            <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-light">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </section>

        <?php if (!$record): ?>
            <section class="nurse-card-panel">
                <div class="nurse-empty-state">
                    <i class="bi bi-journal-x"></i>
                    <h2>Medical record not found</h2>
                    <p>The selected record may have been removed or the link is missing an ID.</p>
                    <a class="btn btn-primary" href="<?= url('views/nurse/medical-records/medical-records.php') ?>">Return to records</a>
                </div>
            </section>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <section class="nurse-card-panel">
                        <div class="nurse-card-header">
                            <div>
                                <span class="nurse-kicker">Record form</span>
                                <h2>Update health details</h2>
                                <p>Student reference: <?= e($record['studentId'] ?? 'Not linked') ?></p>
                            </div>
                        </div>

                        <form method="POST" action="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode($recordId)) ?>" class="row g-3 nurse-profile-form">
                            <div class="col-md-6">
                                <label class="form-label">Diagnosis</label>
                                <input type="text" name="diagnosis" class="form-control" value="<?= e($record['diagnosis'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Treatment</label>
                                <input type="text" name="treatment" class="form-control" value="<?= e($record['treatment'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <?php foreach (['normal', 'moderate', 'critical'] as $severity): ?>
                                        <option value="<?= e($severity) ?>" <?= (($record['severity'] ?? 'normal') === $severity) ? 'selected' : '' ?>>
                                            <?= e(ucfirst($severity)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="5"><?= e($record['notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check2-circle"></i> Update record
                                </button>
                                <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="nurse-side-card">
                        <h2>Record snapshot</h2>
                        <div class="nurse-info-list">
                            <div><i class="bi bi-person"></i><span>Student</span><strong><?= e($record['studentId'] ?? 'Not linked') ?></strong></div>
                            <div><i class="bi bi-heart-pulse"></i><span>Severity</span><strong><?= e(ucfirst((string) ($record['severity'] ?? 'normal'))) ?></strong></div>
                            <div><i class="bi bi-clock-history"></i><span>Created</span><strong><?= e($record['createdAt'] ?? 'Not recorded') ?></strong></div>
                        </div>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
