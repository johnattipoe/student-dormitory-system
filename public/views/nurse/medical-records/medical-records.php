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

$records = (new MedicalService())->all();
$totalRecords = count($records);
$criticalRecords = count(array_filter($records, fn($record) => strtolower((string) ($record['severity'] ?? '')) === 'critical'));
$moderateRecords = count(array_filter($records, fn($record) => strtolower((string) ($record['severity'] ?? '')) === 'moderate'));

$pageTitle = 'Medical Records';
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
            <div>
                <span class="nurse-kicker"><i class="bi bi-journal-medical"></i> Medical records</span>
                <h1>Student health records</h1>
                <p>Track diagnoses, treatment notes, and severity levels for all student clinic visits.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('views/nurse/medical-records/bulk-import.php') ?>" class="btn btn-light">
                    <i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel
                </a>
                <a href="<?= url('views/nurse/create-record/create-record.php') ?>" class="btn btn-warning">
                    <i class="bi bi-plus-circle"></i> New record
                </a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="nurse-stat"><span>Total records</span><strong><?= e((string) $totalRecords) ?></strong></div></div>
            <div class="col-md-4"><div class="nurse-stat"><span>Moderate cases</span><strong><?= e((string) $moderateRecords) ?></strong></div></div>
            <div class="col-md-4"><div class="nurse-stat"><span>Critical cases</span><strong><?= e((string) $criticalRecords) ?></strong></div></div>
        </div>

        <div class="nurse-card-panel">
            <div class="nurse-card-header">
                <div>
                    <h2>Records table</h2>
                    <p>Use the table tools to search, sort, export, or print records.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle data-table w-100">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Severity</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($records)): ?>
                            <?php foreach ($records as $record): ?>
                                <?php $severity = strtolower((string) ($record['severity'] ?? 'normal')); ?>
                                <tr>
                                    <td><?= e($record['studentId'] ?? 'Not linked') ?></td>
                                    <td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td>
                                    <td><?= e($record['treatment'] ?? 'Not recorded') ?></td>
                                    <td>
                                        <span class="badge <?= $severity === 'critical' ? 'bg-danger' : ($severity === 'moderate' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                            <?= e(ucfirst($severity ?: 'normal')) ?>
                                        </span>
                                    </td>
                                    <td><?= e($record['createdAt'] ?? 'Not recorded') ?></td>
                                    <td class="text-end">
                                        <?php if (!empty($record['id'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode((string) $record['id'])) ?>">Edit</a>
                                        <?php else: ?>
                                            <span class="text-muted small">No ID</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No medical records available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
