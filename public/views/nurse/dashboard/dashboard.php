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

$medicalService = new MedicalService();
$students = StudentService::all();
$records = $medicalService->all();
$todayCases = $medicalService->todayCases();
$emergencyCases = $medicalService->emergencyCases();
$criticalRecords = array_values(array_filter($records, static fn($r) => in_array(strtolower((string) ($r['severity'] ?? 'normal')), ['critical', 'emergency'], true)));
$moderateRecords = array_values(array_filter($records, static fn($r) => strtolower((string) ($r['severity'] ?? 'normal')) === 'moderate'));
$normalRecords = array_values(array_filter($records, static fn($r) => strtolower((string) ($r['severity'] ?? 'normal')) === 'normal'));

usort($records, static fn($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

$pageTitle = 'Nurse Dashboard';
$pageStyles = ['nurse.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-plus-circle', 'label' => 'Create Record', 'href' => url('views/nurse/create-record/create-record.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-file-earmark-medical', 'label' => 'Health Reports', 'href' => url('views/nurse/health-reports/health-reports.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper nurse-portal">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-heart-pulse"></i></div>
            <div>
                <span class="nurse-kicker">Health desk</span>
                <h1>Welcome, <?= e(current_user()['name'] ?? 'Nurse') ?></h1>
                <p>Track student medical records, emergency cases, follow-ups, and daily clinic activity from one workspace.</p>
                <div class="nurse-badges">
                    <span class="badge bg-success"><i class="bi bi-people me-1"></i><?= e((string) count($students)) ?> students</span>
                    <span class="badge bg-info"><i class="bi bi-calendar-day me-1"></i><?= e((string) $todayCases) ?> today</span>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-octagon me-1"></i><?= e((string) $emergencyCases) ?> emergency</span>
                </div>
            </div>
            <div class="nurse-hero-actions">
                <a class="btn btn-light" href="<?= url('views/nurse/create-record/create-record.php') ?>"><i class="bi bi-plus-circle me-1"></i>New record</a>
                <a class="btn btn-primary" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>"><i class="bi bi-exclamation-triangle me-1"></i>Emergencies</a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon green"><i class="bi bi-people"></i></span><div><small>Students</small><strong><?= e((string) count($students)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon blue"><i class="bi bi-journal-medical"></i></span><div><small>Records</small><strong><?= e((string) count($records)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon orange"><i class="bi bi-calendar-day"></i></span><div><small>Today cases</small><strong><?= e((string) $todayCases) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-exclamation-octagon"></i></span><div><small>Emergency</small><strong><?= e((string) $emergencyCases) ?></strong></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="nurse-card-panel mb-4">
                    <div class="nurse-card-header">
                        <div><span class="nurse-kicker">Clinical records</span><h2>Recent medical records</h2><p>Latest diagnoses, treatment notes, and severity flags.</p></div>
                        <a class="btn btn-outline-primary btn-sm" href="<?= url('views/nurse/medical-records/medical-records.php') ?>">Open records</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover data-table w-100">
                            <thead><tr><th>Student</th><th>Diagnosis</th><th>Severity</th><th>Recorded</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$records): ?>
                                <tr><td colspan="5" class="text-center text-muted">No medical records yet.</td></tr>
                            <?php else: foreach (array_slice($records, 0, 10) as $record): ?>
                                <?php $severity = strtolower((string) ($record['severity'] ?? 'normal')); ?>
                                <tr>
                                    <td><?= e($record['studentId'] ?? '-') ?></td>
                                    <td><?= e($record['diagnosis'] ?? '-') ?></td>
                                    <td><span class="badge bg-<?= in_array($severity, ['critical', 'emergency'], true) ? 'danger' : ($severity === 'moderate' ? 'warning' : 'success') ?>"><?= e(ucfirst($severity)) ?></span></td>
                                    <td><?= e($record['createdAt'] ?? '-') ?></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="nurse-card-panel">
                    <div class="nurse-card-header">
                        <div><span class="nurse-kicker">Case balance</span><h2>Health status distribution</h2><p>Quick view of normal, moderate, and emergency cases.</p></div>
                    </div>
                    <div class="nurse-severity-grid">
                        <div><span class="normal"></span><small>Normal</small><strong><?= e((string) count($normalRecords)) ?></strong></div>
                        <div><span class="moderate"></span><small>Moderate</small><strong><?= e((string) count($moderateRecords)) ?></strong></div>
                        <div><span class="critical"></span><small>Critical/Emergency</small><strong><?= e((string) count($criticalRecords)) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="nurse-side-card mb-4">
                    <span class="nurse-kicker">Quick actions</span>
                    <h2>Clinic actions</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-success" href="<?= url('views/nurse/create-record/create-record.php') ?>"><i class="bi bi-plus-circle me-1"></i>Create medical record</a>
                        <a class="btn btn-outline-danger" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>"><i class="bi bi-exclamation-octagon me-1"></i>Emergency cases</a>
                        <a class="btn btn-outline-primary" href="<?= url('views/nurse/students/students.php') ?>"><i class="bi bi-people me-1"></i>Student directory</a>
                        <a class="btn btn-secondary" href="<?= url('views/nurse/health-reports/health-reports.php') ?>"><i class="bi bi-file-earmark-medical me-1"></i>Health reports</a>
                    </div>
                </aside>

                <aside class="nurse-side-card">
                    <span class="nurse-kicker">Care pulse</span>
                    <h2>Clinic summary</h2>
                    <div class="nurse-info-list">
                        <div><i class="bi bi-people text-success"></i><span>Total students</span><strong><?= e((string) count($students)) ?></strong></div>
                        <div><i class="bi bi-journal-medical text-primary"></i><span>Total records</span><strong><?= e((string) count($records)) ?></strong></div>
                        <div><i class="bi bi-calendar-day text-info"></i><span>Today cases</span><strong><?= e((string) $todayCases) ?></strong></div>
                        <div><i class="bi bi-exclamation-triangle text-danger"></i><span>Emergency cases</span><strong><?= e((string) $emergencyCases) ?></strong></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
