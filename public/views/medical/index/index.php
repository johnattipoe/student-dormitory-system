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
$allowedRoles = [ROLE_ADMIN, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\MedicalService;
use App\Services\StudentService;

$pageTitle = 'Medical Records';
$records = (new MedicalService())->all();
$students = [];

try {
    foreach (StudentService::all() as $student) {
        $studentId = (string) ($student['id'] ?? '');
        if ($studentId === '') {
            continue;
        }

        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        $students[$studentId] = [
            'name' => $studentName !== '' ? $studentName : 'Unnamed student',
            'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
        ];
    }
} catch (Throwable $e) {
    $students = [];
}

$totalRecords = count($records);
$todayRecords = count(array_filter($records, static fn(array $record): bool => str_starts_with((string) ($record['createdAt'] ?? ''), date('Y-m-d'))));
$criticalRecords = count(array_filter($records, static fn(array $record): bool => in_array(strtolower((string) ($record['severity'] ?? '')), ['critical', 'emergency', 'severe'], true)));
$moderateRecords = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'moderate'));

$displayDate = static function (?string $value): string {
    if (!$value) {
        return 'Not recorded';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value;
};

$studentLabel = static function (array $record) use ($students): string {
    $studentId = (string) ($record['studentId'] ?? '');
    if ($studentId !== '' && isset($students[$studentId])) {
        return $students[$studentId]['name'] . ' (' . $students[$studentId]['admissionNo'] . ')';
    }

    return $studentId !== '' ? $studentId : 'Not linked';
};

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => url('views/medical/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Medical Records</h5>
                <p class="text-muted mb-0">Fetched from the Nurse portal medical records collection.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 border-0">
                    <span class="text-muted small">Total Records</span>
                    <strong class="fs-3"><?= e((string) $totalRecords) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 border-0">
                    <span class="text-muted small">Today</span>
                    <strong class="fs-3 text-primary"><?= e((string) $todayRecords) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 border-0">
                    <span class="text-muted small">Moderate</span>
                    <strong class="fs-3 text-warning"><?= e((string) $moderateRecords) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 border-0">
                    <span class="text-muted small">Critical / Emergency</span>
                    <strong class="fs-3 text-danger"><?= e((string) $criticalRecords) ?></strong>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table w-100">
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Notes</th>
                        <th>Severity</th>
                        <th>Recorded By</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No medical records found from the Nurse portal.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <?php
                            $severity = strtolower((string) ($record['severity'] ?? 'normal'));
                            $severityClass = in_array($severity, ['critical', 'emergency', 'severe'], true)
                                ? 'danger'
                                : ($severity === 'moderate' ? 'warning text-dark' : 'success');
                            ?>
                            <tr>
                                <td><strong><?= e($studentLabel($record)) ?></strong></td>
                                <td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td>
                                <td><?= e($record['treatment'] ?? 'Not recorded') ?></td>
                                <td><?= e($record['notes'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= e($severityClass) ?>">
                                        <?= e(ucfirst($severity ?: 'normal')) ?>
                                    </span>
                                </td>
                                <td><?= e($record['recordedBy'] ?? '-') ?></td>
                                <td><?= e($displayDate($record['createdAt'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
