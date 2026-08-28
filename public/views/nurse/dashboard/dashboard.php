<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\MedicalService;
use App\Services\StudentService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$nurseName = trim(($user['name'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))) ?: 'Nurse';

$medicalService = new MedicalService();
$students = StudentService::all();
$records  = $medicalService->all();
$todayCases    = (int) $medicalService->todayCases();
$emergencyCases = (int) $medicalService->emergencyCases();

$criticalRecords = array_values(array_filter($records, static fn($r) => in_array(strtolower((string)($r['severity'] ?? 'normal')), ['severe', 'critical', 'emergency'], true)));
$moderateRecords = array_values(array_filter($records, static fn($r) => strtolower((string)($r['severity'] ?? 'normal')) === 'moderate'));

// Fetch announcements for nurses
$firebase = FirebaseService::getInstance();
$allAnn = $firebase->getCollection('announcements', [], 50);
$nurseAnn = array_values(array_filter($allAnn, fn($a) => ($a['status'] ?? 'published') === 'published' && in_array($a['audience'] ?? 'all', ['all', 'role'])));
usort($nurseAnn, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$topAnn = array_slice($nurseAnn, 0, 3);

usort($records, static fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$recentRecords = array_slice($records, 0, 7);

$totalStudents  = count($students);
$totalRecords   = count($records);
$criticalCount  = count($criticalRecords);
$moderateCount  = count($moderateRecords);

$pageTitle  = 'Nurse Dashboard';
$pageStyles = ['nurse.css'];
$navItems = [
    ['icon' => 'bi-speedometer2',          'label' => 'Dashboard',       'href' => url('views/nurse/dashboard/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people',                'label' => 'Students',         'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical',       'label' => 'Medical Records',  'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-plus-circle',           'label' => 'Create Record',    'href' => url('views/nurse/create-record/create-record.php')],
    ['icon' => 'bi-exclamation-triangle',  'label' => 'Emergency Cases',  'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-file-earmark-medical',  'label' => 'Health Reports',   'href' => url('views/nurse/health-reports/health-reports.php')],
    ['icon' => 'bi-bell',                  'label' => 'Notifications',    'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Welcome Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>Welcome, <?= e($nurseName) ?>
                </h4>
                <p class="text-muted mb-0">
                    Campus Health &amp; Infirmary Desk &bull; <?= e(date('l, F j, Y')) ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/nurse/create-record/create-record.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> New Medical Record
                </a>
                <a href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-exclamation-octagon me-1"></i> Emergencies
                </a>
                <a href="<?= url('views/nurse/emergency-alerts/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-telephone-inbound me-1"></i> Alert Desk
                </a>
            </div>
        </div>

        <!-- Primary KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Students</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalStudents) ?></h3>
                            <span class="small text-muted">Enrolled campus residents</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Today's Cases</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $todayCases) ?></h3>
                            <span class="small text-muted">Clinic visits today</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-calendar-day fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= $emergencyCases > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Emergency Cases</span>
                            <h3 class="fw-bold my-1 text-<?= $emergencyCases > 0 ? 'danger' : 'dark' ?>"><?= e((string) $emergencyCases) ?></h3>
                            <span class="small text-muted"><?= $criticalCount ?> critical records</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">All Medical Records</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $totalRecords) ?></h3>
                            <span class="small text-muted"><?= $moderateCount ?> moderate severity</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-journal-medical fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Analytics Strip -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">CRITICAL SEVERITY</small>
                    <div class="fs-4 fw-bold text-danger mt-1"><?= $criticalCount ?> Cases</div>
                    <small class="text-muted">Require immediate attention</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">MODERATE SEVERITY</small>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= $moderateCount ?> Cases</div>
                    <small class="text-muted">Under monitoring</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">TODAY'S LOAD</small>
                    <div class="fs-4 fw-bold text-info mt-1"><?= $todayCases ?> Visits</div>
                    <small class="text-muted">Clinic consultations today</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">INFIRMARY STATUS</small>
                    <div class="fs-4 fw-bold text-success mt-1">Open</div>
                    <small class="text-muted">Health desk operational</small>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="row g-4 mb-4">

            <!-- Left: Recent Medical Records -->
            <div class="col-lg-8">
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-journal-medical me-2 text-primary"></i>Recent Medical Records</h6>
                            <small class="text-muted">Latest diagnoses, treatment notes, and severity flags</small>
                        </div>
                        <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-secondary btn-sm">
                            All Records <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Diagnosis</th>
                                        <th>Severity</th>
                                        <th>Recorded</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentRecords)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-clipboard2-x fs-3 d-block text-secondary mb-1"></i>
                                            No medical records yet.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentRecords as $rec): ?>
                                            <?php
                                            $sev = strtolower((string)($rec['severity'] ?? 'normal'));
                                            $sevBadge = in_array($sev, ['severe', 'critical', 'emergency'], true) ? 'bg-danger' : ($sev === 'moderate' ? 'bg-warning text-dark' : 'bg-success');
                                            $recId = (string)($rec['id'] ?? '');
                                            ?>
                                            <tr>
                                                <td><strong><?= e($rec['studentName'] ?? $rec['studentId'] ?? '—') ?></strong></td>
                                                <td><?= e(mb_strimwidth((string)($rec['diagnosis'] ?? '—'), 0, 35, '…')) ?></td>
                                                <td><span class="badge <?= $sevBadge ?>"><?= ucfirst(e($sev)) ?></span></td>
                                                <td><small class="text-muted"><?= e(substr((string)($rec['createdAt'] ?? '—'), 0, 10)) ?></small></td>
                                                <td class="text-end">
                                                    <?php if ($recId !== ''): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode($recId)) ?>">Edit</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Quick Actions + Bulletins -->
            <div class="col-lg-4">

                <!-- Quick Actions -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Health Desk Actions</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= url('views/nurse/create-record/create-record.php') ?>" class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-plus-circle fs-3 mb-1"></i>
                                    <span class="small fw-bold">New Record</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>" class="btn btn-outline-danger w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-exclamation-octagon fs-3 mb-1"></i>
                                    <span class="small fw-bold">Emergencies</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/nurse/students/students.php') ?>" class="btn btn-outline-info w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-people fs-3 mb-1"></i>
                                    <span class="small fw-bold">Students</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/nurse/medical-records/medical-records.php') ?>" class="btn btn-outline-success w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-journal-medical fs-3 mb-1"></i>
                                    <span class="small fw-bold">All Records</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/nurse/health-reports/health-reports.php') ?>" class="btn btn-outline-secondary w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-file-earmark-medical fs-3 mb-1"></i>
                                    <span class="small fw-bold">Health Reports</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/nurse/activity-logs/index.php') ?>" class="btn btn-outline-dark w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-clock-history fs-3 mb-1"></i>
                                    <span class="small fw-bold">Activity Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulletins -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Health Bulletins</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($topAnn)): ?>
                            <p class="text-muted small text-center my-2">No bulletins posted.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($topAnn as $ann): ?>
                                    <div class="p-2 rounded-3 bg-light border">
                                        <div class="fw-bold small text-dark"><?= e($ann['title'] ?? 'Notice') ?></div>
                                        <p class="text-muted small mb-0"><?= e(mb_strimwidth((string)($ann['message'] ?? ''), 0, 70, '…')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
