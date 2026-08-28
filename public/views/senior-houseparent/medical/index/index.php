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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\MedicalService;
use App\Services\StudentService;

$houseId = (string) (current_user()['houseId'] ?? '');
$students = StudentService::all($houseId !== '' ? $houseId : null);
$studentMap = [];
foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId !== '') {
        $studentMap[$studentId] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Unnamed student';
    }
}
$records = array_values(array_filter((new MedicalService())->all(), static fn(array $record): bool => isset($studentMap[(string) ($record['studentId'] ?? '')])));
$severityCount = static function (string $severity, array $records): int {
    return count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? 'normal')) === $severity));
};
$displayDate = static function (?string $value): string {
    if (!$value) return 'Not recorded';
    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value;
};

$pageTitle = 'Medical Records';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => url('views/senior-houseparent/medical/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><div><div class="text-muted small text-uppercase fw-semibold">Student welfare</div><h5 class="mb-1">Medical Records</h5><p class="text-muted mb-0">Medical information for students in your house.</p></div><span class="badge bg-primary"><i class="bi bi-heart-pulse me-1"></i><?= e((string) count($records)) ?> records</span></div>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Total Records</small><strong class="fs-2 mt-2"><?= e((string) count($records)) ?></strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Today</small><strong class="fs-2 text-primary mt-2"><?= e((string) count(array_filter($records, static fn(array $record): bool => str_starts_with((string) ($record['createdAt'] ?? ''), date('Y-m-d'))))) ?></strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Moderate</small><strong class="fs-2 text-warning mt-2"><?= e((string) $severityCount('moderate', $records)) ?></strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card stat-card p-3 h-100"><small class="text-muted">Critical</small><strong class="fs-2 text-danger mt-2"><?= e((string) count(array_filter($records, static fn(array $record): bool => in_array(strtolower((string) ($record['severity'] ?? '')), ['critical', 'emergency', 'severe'], true)))) ?></strong></div></div>
        </div>
        <div class="card stat-card p-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Diagnosis</th><th>Treatment</th><th>Severity</th><th>Recorded By</th><th>Created</th></tr></thead><tbody>
        <?php if (!$records): ?><tr><td colspan="6" class="text-center text-muted py-4">No medical records found for your house.</td></tr>
        <?php else: foreach ($records as $record): ?><?php $severity = strtolower((string) ($record['severity'] ?? 'normal')); $severityClass = in_array($severity, ['critical', 'emergency', 'severe'], true) ? 'danger' : ($severity === 'moderate' ? 'warning text-dark' : 'success'); ?><tr><td><strong><?= e($studentMap[(string) ($record['studentId'] ?? '')] ?? 'Unknown student') ?></strong></td><td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td><td><?= e($record['treatment'] ?? 'Not recorded') ?></td><td><span class="badge bg-<?= e($severityClass) ?>"><?= e(ucfirst($severity ?: 'normal')) ?></span></td><td><?= e($record['recordedBy'] ?? '-') ?></td><td><?= e($displayDate($record['createdAt'] ?? null)) ?></td></tr><?php endforeach; endif; ?>
        </tbody></table></div></div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
