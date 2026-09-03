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
$records = $medicalService->all();
$fromDate = trim((string) ($_GET['from'] ?? ''));
$toDate = trim((string) ($_GET['to'] ?? ''));
$severityFilter = strtolower(trim((string) ($_GET['severity'] ?? 'all')));
$validSeverities = ['normal', 'moderate', 'severe', 'critical', 'emergency'];
if (!in_array($severityFilter, array_merge(['all'], $validSeverities), true)) {
    $severityFilter = 'all';
}
$filteredRecords = array_values(array_filter($records, static function (array $record) use ($fromDate, $toDate, $severityFilter): bool {
    $createdDate = substr((string) ($record['createdAt'] ?? ''), 0, 10);
    $severity = strtolower((string) ($record['severity'] ?? 'normal'));
    return ($fromDate === '' || $createdDate >= $fromDate)
        && ($toDate === '' || $createdDate <= $toDate)
        && ($severityFilter === 'all' || $severity === $severityFilter);
}));

$report = ['total' => count($filteredRecords), 'normal' => 0, 'moderate' => 0, 'severe' => 0, 'critical' => 0, 'emergency' => 0];
foreach ($filteredRecords as $record) {
    $severity = strtolower((string) ($record['severity'] ?? 'normal'));
    if (isset($report[$severity])) $report[$severity]++;
}
$recentRecords = array_slice($filteredRecords, 0, 8);

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="health-report-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student', 'Diagnosis', 'Treatment', 'Notes', 'Severity', 'Created At']);
    foreach ($filteredRecords as $record) {
        fputcsv($output, [
            $record['studentName'] ?? $record['studentId'] ?? 'Not linked',
            $record['diagnosis'] ?? '',
            $record['treatment'] ?? '',
            $record['notes'] ?? '',
            $record['severity'] ?? 'normal',
            $record['createdAt'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

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

        <section class="nurse-card-panel mb-4">
            <div class="nurse-card-header">
                <div>
                    <span class="nurse-kicker">Report filters</span>
                    <h2>Focus the reporting period</h2>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/nurse/health-reports/health-reports.php?from=' . urlencode($fromDate) . '&to=' . urlencode($toDate) . '&severity=' . urlencode($severityFilter) . '&download=csv') ?>">
                    <i class="bi bi-download me-1"></i>Download CSV
                </a>
            </div>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-semibold">From date</label><input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>"></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">To date</label><input type="date" name="to" class="form-control" value="<?= e($toDate) ?>"></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Severity</label><select name="severity" class="form-select"><option value="all">All severities</option><?php foreach ($validSeverities as $severity): ?><option value="<?= e($severity) ?>" <?= $severityFilter === $severity ? 'selected' : '' ?>><?= e(ucfirst($severity)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button><a class="btn btn-outline-secondary" href="<?= url('views/nurse/health-reports/health-reports.php') ?>">Reset</a></div>
            </form>
            <small class="text-muted d-block mt-3">Showing <?= e((string) count($filteredRecords)) ?> matching record(s).</small>
        </section>

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
