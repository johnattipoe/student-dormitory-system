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

$medicalService = new MedicalService();
$report = $medicalService->reports();
$records = $medicalService->all();
$recentRecords = array_slice($records, 0, 8);

$pageTitle = 'Health Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Health Reports', 'href' => url('views/nurse/health-reports/health-reports.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content nurse-portal">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-bar-chart-line"></i></div>
            <div>
                <span class="nurse-kicker">Clinic analytics</span>
                <h1>Health reports</h1>
                <p>Monitor medical record volume, severity distribution, and recent clinic activity.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/nurse/medical-records/medical-records.php') ?>">
                <i class="bi bi-journal-medical"></i> Records
            </a>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon green"><i class="bi bi-folder2-open"></i></span><div><small>Total records</small><strong><?= e((string) ($report['total'] ?? 0)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon green"><i class="bi bi-check2-circle"></i></span><div><small>Normal</small><strong><?= e((string) ($report['normal'] ?? 0)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon orange"><i class="bi bi-exclamation-triangle"></i></span><div><small>Moderate / Severe</small><strong><?= e((string) (($report['moderate'] ?? 0) + ($report['severe'] ?? 0))) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-activity"></i></span><div><small>Critical</small><strong><?= e((string) ($report['critical'] ?? 0)) ?></strong></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="nurse-card-panel h-100">
                    <div class="nurse-card-header">
                        <div>
                            <span class="nurse-kicker">Severity</span>
                            <h2>Distribution</h2>
                            <p>Current medical records grouped by urgency.</p>
                        </div>
                    </div>
                    <div class="nurse-severity-grid">
                        <div><span class="normal"></span><small>Normal</small><strong><?= e((string) ($report['normal'] ?? 0)) ?></strong></div>
                        <div><span class="moderate"></span><small>Moderate / Severe</small><strong><?= e((string) (($report['moderate'] ?? 0) + ($report['severe'] ?? 0))) ?></strong></div>
                        <div><span class="critical"></span><small>Emergency / Critical</small><strong><?= e((string) (($report['emergency'] ?? 0) + ($report['critical'] ?? 0))) ?></strong></div>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="nurse-card-panel h-100">
                    <div class="nurse-card-header">
                        <div>
                            <span class="nurse-kicker">Recent activity</span>
                            <h2>Latest records</h2>
                            <p>Most recent clinic entries for fast review.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle nurse-data-table">
                            <thead><tr><th>Student</th><th>Diagnosis</th><th>Severity</th><th>Created</th></tr></thead>
                            <tbody>
                                <?php if (!empty($recentRecords)): ?>
                                    <?php foreach ($recentRecords as $record): ?>
                                        <?php $severity = strtolower((string) ($record['severity'] ?? 'normal')); ?>
                                        <tr>
                                            <td><?= e($record['studentId'] ?? 'Not linked') ?></td>
                                            <td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td>
                                            <td><span class="badge <?= $severity === 'critical' ? 'bg-danger' : ($severity === 'moderate' ? 'bg-warning text-dark' : 'bg-success') ?>"><?= e(ucfirst($severity ?: 'normal')) ?></span></td>
                                            <td><?= e($record['createdAt'] ?? 'Not recorded') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No records available for reporting.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
