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

$medicalService = new MedicalService();
$recordId = sanitize($_GET['id'] ?? '');
$record = $medicalService->find($recordId);

// Resolve student name & details from studentId stored in record
$linkedStudent = null;
if ($record && !empty($record['studentId'])) {
    $linkedStudent = StudentService::find((string) $record['studentId']);
}
$studentDisplayName = '';
if ($linkedStudent) {
    $studentDisplayName = trim(($linkedStudent['firstName'] ?? '') . ' ' . ($linkedStudent['lastName'] ?? ''));
}
if ($studentDisplayName === '' && $record) {
    $studentDisplayName = (string) ($record['studentName'] ?? '');
}
$studentAdmissionNo = (string) ($linkedStudent['admissionNo'] ?? '');
$studentClass       = (string) ($linkedStudent['class'] ?? $linkedStudent['form'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($recordId === '') {
        flash('error', 'Medical record ID is required.');
        redirect(url('views/nurse/medical-records/medical-records.php'));
    }

    $result = $medicalService->update($recordId, [
        'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
        'treatment' => sanitize($_POST['treatment'] ?? ''),
        'notes'     => sanitize($_POST['notes'] ?? ''),
        'severity'  => sanitize($_POST['severity'] ?? 'normal'),
        'updatedBy' => current_user_id(),
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/nurse/medical-records/medical-records.php'));
}

$pageTitle = 'Edit Medical Record';
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
            <?php
            // Severity badge colours
            $sevColor = match(strtolower((string) ($record['severity'] ?? 'normal'))) {
                'critical' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'dot' => '#ef4444', 'badge' => 'danger'],
                'moderate' => ['bg' => '#fef3c7', 'text' => '#92400e', 'dot' => '#f59e0b', 'badge' => 'warning'],
                default    => ['bg' => '#dcfce7', 'text' => '#166534', 'dot' => '#22c55e', 'badge' => 'success'],
            };

            // Format date nicely
            $rawDate = (string) ($record['createdAt'] ?? '');
            $formattedDate = '—';
            if ($rawDate !== '') {
                try {
                    $dt = new DateTime($rawDate);
                    $formattedDate = $dt->format('d M Y, g:i A');
                } catch (\Exception $e) {
                    $formattedDate = $rawDate;
                }
            }

            $updatedDate = '—';
            if (!empty($record['updatedAt'])) {
                try {
                    $dt2 = new DateTime((string) $record['updatedAt']);
                    $updatedDate = $dt2->format('d M Y, g:i A');
                } catch (\Exception $e) {
                    $updatedDate = $record['updatedAt'];
                }
            }
            ?>
            <div class="row g-4">
                <!-- Left: Edit Form -->
                <div class="col-lg-8">
                    <section class="nurse-card-panel">
                        <div class="nurse-card-header">
                            <div>
                                <span class="nurse-kicker">Record form</span>
                                <h2>Update health details</h2>
                                <?php if ($studentDisplayName !== ''): ?>
                                    <p class="mb-0">
                                        Editing record for
                                        <strong class="text-success"><?= e($studentDisplayName) ?></strong>
                                        <?php if ($studentAdmissionNo !== ''): ?>
                                            &bull; <span class="text-muted small font-monospace"><?= e($studentAdmissionNo) ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted small mb-0">Student reference: <?= e($record['studentId'] ?? 'Not linked') ?></p>
                                <?php endif; ?>
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
                                    <i class="bi bi-check2-circle me-1"></i> Update record
                                </button>
                                <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- Right: Record Snapshot -->
                <div class="col-lg-4">
                    <aside class="nurse-side-card p-0 overflow-hidden" style="border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.07);">
                        <!-- Header Banner -->
                        <div class="d-flex align-items-center gap-3 p-3 pb-3" style="background: linear-gradient(135deg, #064e3b 0%, #059669 100%);">
                            <div class="d-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20" style="width: 46px; height: 46px; flex-shrink: 0;">
                                <i class="bi bi-clipboard2-pulse text-white fs-5"></i>
                            </div>
                            <div>
                                <div class="text-white fw-bold fs-6 lh-sm">Record Snapshot</div>
                                <div class="text-white opacity-75 small">Quick info about this record</div>
                            </div>
                        </div>

                        <div class="p-3 d-flex flex-column gap-3">

                            <!-- Student Identity Card -->
                            <div class="rounded-3 p-3 d-flex align-items-start gap-2" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white" style="width: 38px; height: 38px; flex-shrink: 0; font-size: .9rem;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: .04em; font-size: .7rem;">Student</div>
                                    <?php if ($studentDisplayName !== ''): ?>
                                        <div class="fw-bold text-dark lh-sm" style="font-size: .95rem; overflow-wrap: anywhere;"><?= e($studentDisplayName) ?></div>
                                        <?php if ($studentAdmissionNo !== ''): ?>
                                            <div class="mt-1 d-flex flex-wrap gap-1">
                                                <span class="badge bg-white text-dark border font-monospace" style="font-size: .72rem;">ID: <?= e($studentAdmissionNo) ?></span>
                                                <?php if ($studentClass !== ''): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: .72rem;"><?= e($studentClass) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-muted small font-monospace" style="overflow-wrap: anywhere;"><?= e($record['studentId'] ?? 'Not linked') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Severity -->
                            <div class="rounded-3 p-3 d-flex align-items-center gap-2" style="background: <?= $sevColor['bg'] ?>; border: 1px solid <?= $sevColor['dot'] ?>33;">
                                <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: <?= $sevColor['dot'] ?>; flex-shrink: 0;"></span>
                                <div class="flex-grow-1">
                                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: .04em; font-size: .7rem;">Severity</div>
                                    <div class="fw-bold" style="color: <?= $sevColor['text'] ?>; font-size: .95rem;"><?= e(ucfirst((string) ($record['severity'] ?? 'Normal'))) ?></div>
                                </div>
                                <span class="badge rounded-pill text-bg-<?= $sevColor['badge'] ?> px-2 py-1"><?= e(ucfirst((string) ($record['severity'] ?? 'Normal'))) ?></span>
                            </div>

                            <!-- Diagnosis & Treatment quick view -->
                            <?php if (!empty($record['diagnosis'])): ?>
                            <div class="rounded-3 p-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: .04em; font-size: .7rem;"><i class="bi bi-search-heart me-1"></i>Diagnosis</div>
                                <div class="fw-semibold text-dark" style="font-size: .9rem;"><?= e($record['diagnosis']) ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($record['treatment'])): ?>
                            <div class="rounded-3 p-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: .04em; font-size: .7rem;"><i class="bi bi-capsule me-1"></i>Treatment</div>
                                <div class="fw-semibold text-dark" style="font-size: .9rem;"><?= e($record['treatment']) ?></div>
                            </div>
                            <?php endif; ?>

                            <!-- Timestamps -->
                            <div class="rounded-3 p-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar-plus text-muted" style="font-size: .85rem; width: 16px;"></i>
                                        <div>
                                            <div class="text-muted" style="font-size: .7rem; letter-spacing: .04em; font-weight: 600; text-transform: uppercase;">Created</div>
                                            <div class="fw-semibold text-dark" style="font-size: .82rem;"><?= e($formattedDate) ?></div>
                                        </div>
                                    </div>
                                    <?php if ($updatedDate !== '—'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-pencil text-muted" style="font-size: .85rem; width: 16px;"></i>
                                        <div>
                                            <div class="text-muted" style="font-size: .7rem; letter-spacing: .04em; font-weight: 600; text-transform: uppercase;">Last Updated</div>
                                            <div class="fw-semibold text-dark" style="font-size: .82rem;"><?= e($updatedDate) ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
