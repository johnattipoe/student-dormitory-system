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

$allRecords = (new MedicalService())->all();
$cases = array_values(array_filter($allRecords, function ($record) {
    $severity = strtolower((string) ($record['severity'] ?? ''));
    return in_array($severity, ['emergency', 'critical'], true);
}));

$pageTitle = 'Emergency Cases';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-3">
            <h5 class="mb-3">Emergency Cases</h5>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Case</th>
                        <th>Severity</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($cases)): ?>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?= e($case['studentId'] ?? '—') ?></td>
                                <td><?= e($case['diagnosis'] ?? $case['title'] ?? '—') ?></td>
                                <td><?= e($case['severity'] ?? 'moderate') ?></td>
                                <td><?= e($case['createdAt'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No emergency cases reported.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
