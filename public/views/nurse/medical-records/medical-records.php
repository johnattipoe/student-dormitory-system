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

$records = (new MedicalService())->all();
$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId !== '') {
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        $students[$studentId] = [
            'name' => $studentName !== '' ? $studentName : 'Unnamed student',
            'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
        ];
    }
}
$studentLabel = static function (array $record) use ($students): string {
    $studentId = (string) ($record['studentId'] ?? '');
    if ($studentId !== '' && isset($students[$studentId])) {
        return $students[$studentId]['name'] . ' (' . $students[$studentId]['admissionNo'] . ')';
    }

    return $studentId !== '' ? $studentId : 'Not linked';
};
$totalRecords = count($records);
$criticalRecords = count(array_filter($records, fn($record) => in_array(strtolower((string) ($record['severity'] ?? '')), ['severe', 'critical', 'emergency'], true)));
$moderateRecords = count(array_filter($records, fn($record) => strtolower((string) ($record['severity'] ?? '')) === 'moderate'));
$routineRecords = count(array_filter($records, fn($record) => in_array(strtolower((string) ($record['severity'] ?? 'normal')), ['normal', 'minor', 'routine', 'mild'], true)));

$pageTitle = 'Medical Records';
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
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>Campus Sickbay Clinical Records
                </h4>
                <p class="text-muted mb-0">Track diagnoses, prescriptions, treatment progress, and severity classifications</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('views/nurse/medical-records/bulk-import.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload CSV/Excel
                </a>
                <a href="<?= url('views/nurse/create-record/create-record.php') ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Health Record
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Logs</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalRecords) ?></h3>
                            <span class="small text-muted">Clinic attendances</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-medical fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Critical / Emergency</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $criticalRecords) ?></h3>
                            <span class="small text-muted">Immediate care needed</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Moderate Cases</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $moderateRecords) ?></h3>
                            <span class="small text-muted">Under observation</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-bandaid fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Routine Care</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $routineRecords) ?></h3>
                            <span class="small text-muted">Mild ailments</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check2-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Records Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-journal-medical me-2 text-danger"></i>Clinical Attendance Records</h6>
                <small class="text-muted">Showing <?= count($records) ?> entries</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Resident Student</th>
                                <th>Clinical Diagnosis</th>
                                <th>Treatment &amp; Prescriptions</th>
                                <th>Severity</th>
                                <th>Visit Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($records)): ?>
                                <?php foreach ($records as $record): ?>
                                    <?php 
                                    $severity = strtolower((string) ($record['severity'] ?? 'normal'));
                                    $sBadge = match(true) {
                                        in_array($severity, ['severe', 'critical', 'emergency'], true) => 'bg-danger',
                                        $severity === 'moderate' => 'bg-warning text-dark',
                                        default => 'bg-success',
                                    };
                                    $rId = (string) ($record['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><strong class="text-dark"><?= e($studentLabel($record)) ?></strong></td>
                                        <td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td>
                                        <td><small class="text-muted"><?= e($record['treatment'] ?? 'Not recorded') ?></small></td>
                                        <td><span class="badge <?= $sBadge ?>"><?= ucfirst(e($severity ?: 'normal')) ?></span></td>
                                        <td><small class="text-muted"><?= e($record['createdAt'] ?? '—') ?></small></td>
                                        <td class="text-end">
                                            <?php if ($rId !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode($rId)) ?>" title="Edit Record">
                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No medical records registered.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
