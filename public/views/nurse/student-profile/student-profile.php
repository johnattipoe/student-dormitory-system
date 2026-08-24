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
use App\Services\StudentService;

$studentId = sanitize($_GET['id'] ?? '');
$student = $studentId !== '' ? StudentService::find($studentId) : null;
$records = (new MedicalService())->all();
$studentRecords = array_values(array_filter($records, static fn(array $record): bool => (string) ($record['studentId'] ?? '') === (string) ($student['id'] ?? $studentId)));
$latestRecords = array_slice($studentRecords, 0, 5);

$name = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
$initials = strtoupper(substr(trim(($student['firstName'] ?? 'S')), 0, 1) . substr(trim(($student['lastName'] ?? 'D')), 0, 1));

$pageTitle = 'Student Medical Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php'), 'active' => true],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
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
        <?php if (!$student): ?>
            <section class="nurse-card-panel">
                <div class="nurse-empty-state">
                    <i class="bi bi-person-x"></i>
                    <h2>Student not found</h2>
                    <p>The profile link is missing or the student record is no longer available.</p>
                    <a class="btn btn-primary" href="<?= url('views/nurse/students/students.php') ?>">Back to students</a>
                </div>
            </section>
        <?php else: ?>
            <section class="nurse-student-hero mb-4">
                <div class="nurse-student-avatar"><?= e($initials) ?></div>
                <div class="nurse-student-heading">
                    <span class="nurse-kicker">Student medical profile</span>
                    <h1><?= e($name !== '' ? $name : 'Unnamed student') ?></h1>
                    <p>Review student identity, contact details, room information, and medical record history.</p>
                    <div class="nurse-badges">
                        <span class="badge bg-success"><?= e(ucfirst((string) ($student['status'] ?? 'active'))) ?></span>
                        <span class="badge bg-info text-dark"><?= e((string) count($studentRecords)) ?> medical record<?= count($studentRecords) === 1 ? '' : 's' ?></span>
                    </div>
                </div>
                <div class="nurse-hero-actions">
                    <a class="btn btn-light" href="<?= url('views/nurse/students/students.php') ?>"><i class="bi bi-arrow-left"></i> Students</a>
                    <a class="btn btn-warning" href="<?= url('views/nurse/create-record/create-record.php') ?>"><i class="bi bi-plus-circle"></i> New record</a>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-7">
                    <section class="nurse-card-panel">
                        <div class="nurse-card-header">
                            <div>
                                <span class="nurse-kicker">Profile</span>
                                <h2>Student details</h2>
                                <p>Core information used during clinic registration and follow-up.</p>
                            </div>
                        </div>
                        <dl class="row nurse-profile-details">
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= e($student['email'] ?? 'Not provided') ?></dd>
                            <dt class="col-sm-4">Student ID</dt>
                            <dd class="col-sm-8"><?= e($student['studentId'] ?? $student['admissionNo'] ?? $student['id'] ?? 'Not assigned') ?></dd>
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?= e($student['phone'] ?? 'Not provided') ?></dd>
                            <dt class="col-sm-4">Course</dt>
                            <dd class="col-sm-8"><?= e($student['course'] ?? 'Not specified') ?></dd>
                            <dt class="col-sm-4">Level</dt>
                            <dd class="col-sm-8"><?= e($student['level'] ?? 'Not specified') ?></dd>
                            <dt class="col-sm-4">Room</dt>
                            <dd class="col-sm-8"><?= e($student['roomId'] ?? 'Not assigned') ?></dd>
                        </dl>
                    </section>
                </div>

                <div class="col-lg-5">
                    <section class="nurse-side-card h-100">
                        <h2>Care summary</h2>
                        <div class="nurse-info-list">
                            <div><i class="bi bi-folder2-open"></i><span>Total records</span><strong><?= e((string) count($studentRecords)) ?></strong></div>
                            <div><i class="bi bi-heart-pulse"></i><span>Latest severity</span><strong><?= e(ucfirst((string) ($latestRecords[0]['severity'] ?? 'No record'))) ?></strong></div>
                            <div><i class="bi bi-calendar2-check"></i><span>Latest visit</span><strong><?= e($latestRecords[0]['createdAt'] ?? 'No visit recorded') ?></strong></div>
                        </div>
                    </section>
                </div>
            </div>

            <section class="nurse-card-panel mt-4">
                <div class="nurse-card-header">
                    <div>
                        <span class="nurse-kicker">Medical history</span>
                        <h2>Recent records</h2>
                        <p>Latest medical entries connected to this student.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle nurse-data-table">
                        <thead><tr><th>Diagnosis</th><th>Treatment</th><th>Severity</th><th>Created</th></tr></thead>
                        <tbody>
                            <?php if (!empty($latestRecords)): ?>
                                <?php foreach ($latestRecords as $record): ?>
                                    <?php $severity = strtolower((string) ($record['severity'] ?? 'normal')); ?>
                                    <tr>
                                        <td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td>
                                        <td><?= e($record['treatment'] ?? 'Not recorded') ?></td>
                                        <td><span class="badge <?= $severity === 'critical' ? 'bg-danger' : ($severity === 'moderate' ? 'bg-warning text-dark' : 'bg-success') ?>"><?= e(ucfirst($severity ?: 'normal')) ?></span></td>
                                        <td><?= e($record['createdAt'] ?? 'Not recorded') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No medical records found for this student.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
