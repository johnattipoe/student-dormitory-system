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
use App\Services\StudentService;

$medicalService = new MedicalService();
$students = StudentService::all();
$records = $medicalService->all();
$todayCases = $medicalService->todayCases();
$emergencyCases = $medicalService->emergencyCases();

$pageTitle = 'Nurse Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php'), 'active' => true],
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
                <p class="text-muted mb-0">Nurse overview for student health and medical follow-up.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Students</div>
                    <div class="fs-2 fw-bold"><?= e(count($students)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Records</div>
                    <div class="fs-2 fw-bold"><?= e(count($records)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Today</div>
                    <div class="fs-2 fw-bold"><?= e($todayCases) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Emergency</div>
                    <div class="fs-2 fw-bold"><?= e($emergencyCases) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <h6 class="mb-3">Recent Medical Records</h6>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Diagnosis</th>
                        <th>Severity</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No medical records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($records, 0, 8) as $record): ?>
                            <tr>
                                <td><?= e($record['studentId'] ?? '—') ?></td>
                                <td><?= e($record['diagnosis'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($record['severity'] ?? '') === 'critical' ? 'danger' : (($record['severity'] ?? '') === 'moderate' ? 'warning' : 'success') ?>"><?= e($record['severity'] ?? 'normal') ?></span></td>
                                <td><?= e($record['createdAt'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
