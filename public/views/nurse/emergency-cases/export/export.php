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

$pageTitle = 'Emergency Case Export';
$medicalService = new MedicalService();
$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') continue;

    $students[$studentId] = [
        'name' => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Unnamed student',
        'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
    ];
}

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="emergency-cases-export-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student', 'Admission No', 'Severity', 'Status', 'Diagnosis', 'Treatment', 'Created At']);

    foreach ($medicalService->all() as $record) {
        $studentId = (string) ($record['studentId'] ?? '');
        $severity = strtolower((string) ($record['severity'] ?? ''));
        if (!in_array($severity, ['severe', 'critical', 'emergency'], true)) {
            continue;
        }

        fputcsv($output, [
            $students[$studentId]['name'] ?? 'Not linked',
            $students[$studentId]['admissionNo'] ?? $record['studentId'] ?? '',
            ucfirst($severity),
            ucfirst((string) ($record['caseStatus'] ?? 'open')),
            $record['diagnosis'] ?? '',
            $record['treatment'] ?? '',
            $record['createdAt'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

$records = array_values(array_filter($medicalService->all(), static function (array $record): bool {
    return in_array(strtolower((string) ($record['severity'] ?? '')), ['severe', 'critical', 'emergency'], true);
}));

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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-download text-primary me-2"></i>Emergency Case Export</h4>
                <p class="text-muted mb-0">Export urgent clinical cases for sharing and review.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/nurse/emergency-cases/export/export.php?download=csv') ?>">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Download CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Urgent record preview</h6>
                <small class="text-muted"><?= count($records) ?> record(s)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Severity</th>
                                <th>Diagnosis</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No urgent records to export.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $record): ?>
                                    <?php $severity = strtolower((string) ($record['severity'] ?? '')); ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($students[(string) ($record['studentId'] ?? '')]['name'] ?? 'Not linked') ?></td>
                                        <td><span class="badge bg-<?= $severity === 'critical' ? 'warning text-dark' : 'danger' ?>"><?= e(ucfirst($severity)) ?></span></td>
                                        <td><?= e((string) ($record['diagnosis'] ?? 'Not recorded')) ?></td>
                                        <td><span class="badge bg-<?= strtolower((string) ($record['caseStatus'] ?? 'open')) === 'reviewed' ? 'success' : 'danger' ?>"><?= e(ucfirst((string) ($record['caseStatus'] ?? 'open'))) ?></span></td>
                                        <td class="text-muted small"><?= e((string) ($record['createdAt'] ?? '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
