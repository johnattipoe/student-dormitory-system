<?php
// Ensure bootstrap is loaded
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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\VisitorService;
use App\Services\MedicalService;
use App\Services\FirebaseService;

// Get filter parameters
$dateFrom = sanitize($_GET['dateFrom'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo = sanitize($_GET['dateTo'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;
$role = current_user()['role'] ?? '';

// Prepare date range for filtering
$dateStart = strtotime($dateFrom);
$dateEnd = strtotime($dateTo) + 86400;

// Fetch all data
$firebaseService = FirebaseService::getInstance();
$students = ($role === ROLE_ADMIN) ? StudentService::all() : StudentService::all($houseId);
$attendance = $firebaseService->getCollection(COL_ATTENDANCE, [], 1000);
$incidents = (new IncidentService())->all();
$visitors = (new VisitorService())->all();
$medical = (new MedicalService())->all();

// Filter by date range
$attendanceFiltered = array_filter($attendance, function($a) use ($dateStart, $dateEnd) {
    $t = strtotime($a['date'] ?? '');
    return $t >= $dateStart && $t <= $dateEnd;
});

$incidentsFiltered = array_filter($incidents, function($i) use ($dateStart, $dateEnd) {
    $t = strtotime($i['createdAt'] ?? '');
    return $t >= $dateStart && $t <= $dateEnd;
});

$visitorsFiltered = array_filter($visitors, function($v) use ($dateStart, $dateEnd) {
    $t = strtotime($v['visitDate'] ?? '');
    return $t >= $dateStart && $t <= $dateEnd;
});

$medicalFiltered = array_filter($medical, function($m) use ($dateStart, $dateEnd) {
    $t = strtotime($m['createdAt'] ?? '');
    return $t >= $dateStart && $t <= $dateEnd;
});

// Calculate key metrics
$totalAttendance = count($attendanceFiltered);
$presentCount = count(array_filter($attendanceFiltered, fn($a) => ($a['status'] ?? '') === 'present'));
$absentCount = count(array_filter($attendanceFiltered, fn($a) => ($a['status'] ?? '') === 'absent'));
$lateCount = count(array_filter($attendanceFiltered, fn($a) => ($a['status'] ?? '') === 'late'));
$excusedCount = count(array_filter($attendanceFiltered, fn($a) => ($a['status'] ?? '') === 'excused'));

$avgAttendanceRate = $totalAttendance > 0 ? round((($presentCount + $excusedCount) / $totalAttendance) * 100) : 0;

$openIncidents = count(array_filter($incidentsFiltered, fn($i) => ($i['status'] ?? '') === 'open'));
$resolvedIncidents = count(array_filter($incidentsFiltered, fn($i) => ($i['status'] ?? '') === 'resolved'));
$investigatingIncidents = count(array_filter($incidentsFiltered, fn($i) => ($i['status'] ?? '') === 'investigating'));

$highSeverityIncidents = count(array_filter($incidentsFiltered, fn($i) => ($i['severity'] ?? '') === 'high'));

$approvedVisitors = count(array_filter($visitorsFiltered, fn($v) => ($v['status'] ?? '') === 'approved'));
$rejectedVisitors = count(array_filter($visitorsFiltered, fn($v) => ($v['status'] ?? '') === 'rejected'));
$pendingVisitors = count(array_filter($visitorsFiltered, fn($v) => ($v['status'] ?? '') === 'pending'));

$criticalMedical = count(array_filter($medicalFiltered, fn($m) => ($m['severity'] ?? '') === 'critical'));
$moderateMedical = count(array_filter($medicalFiltered, fn($m) => ($m['severity'] ?? '') === 'moderate'));

// Top violated students (most absences)
$absencesByStudent = [];
foreach ($attendanceFiltered as $record) {
    $sid = $record['studentId'] ?? '';
    if ($sid) {
        $absencesByStudent[$sid] = ($absencesByStudent[$sid] ?? 0) + (($record['status'] ?? '') === 'absent' ? 1 : 0);
    }
}
arsort($absencesByStudent);
$topOffenders = array_slice($absencesByStudent, 0, 5);

// Incidents by type
$incidentsByType = [];
foreach ($incidentsFiltered as $incident) {
    $type = $incident['type'] ?? 'other';
    $incidentsByType[$type] = ($incidentsByType[$type] ?? 0) + 1;
}
arsort($incidentsByType);

// Visitors by relationship
$visitorsByRelationship = [];
foreach ($visitorsFiltered as $visitor) {
    $rel = $visitor['relationship'] ?? 'other';
    $visitorsByRelationship[$rel] = ($visitorsByRelationship[$rel] ?? 0) + 1;
}
arsort($visitorsByRelationship);

$pageTitle = 'Analytics Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Analytics', 'href' => url('views/reports/analytics.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Analytics & Insights</h5>

        <!-- Date Range Filter -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/reports/analytics.php">
                <div class="col-md-4">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-50">Filter</button>
                    <a href="<?= url('views/reports/analytics.php') ?>" class="btn btn-outline-secondary btn-sm w-50">Reset</a>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Attendance Rate</div>
                    <div class="fs-2 fw-bold text-success"><?= e($avgAttendanceRate) ?>%</div>
                    <small class="text-muted"><?= e($presentCount) ?> present, <?= e($absentCount) ?> absent</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Incidents</div>
                    <div class="fs-2 fw-bold text-warning"><?= e(count($incidentsFiltered)) ?></div>
                    <small class="text-muted"><?= e($openIncidents) ?> open, <?= e($highSeverityIncidents) ?> high severity</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Visitor Approvals</div>
                    <div class="fs-2 fw-bold text-info"><?= e($approvedVisitors) ?></div>
                    <small class="text-muted"><?= e($pendingVisitors) ?> pending, <?= e($rejectedVisitors) ?> rejected</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Medical Cases</div>
                    <div class="fs-2 fw-bold text-danger"><?= e(count($medicalFiltered)) ?></div>
                    <small class="text-muted"><?= e($criticalMedical) ?> critical, <?= e($moderateMedical) ?> moderate</small>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row g-3 mb-4">
            <!-- Attendance Breakdown -->
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Attendance Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><span class="badge bg-success">Present</span></td>
                                <td class="text-end"><?= e($presentCount) ?></td>
                                <td class="text-end text-muted small"><?= $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-danger">Absent</span></td>
                                <td class="text-end"><?= e($absentCount) ?></td>
                                <td class="text-end text-muted small"><?= $totalAttendance > 0 ? round(($absentCount / $totalAttendance) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Late</span></td>
                                <td class="text-end"><?= e($lateCount) ?></td>
                                <td class="text-end text-muted small"><?= $totalAttendance > 0 ? round(($lateCount / $totalAttendance) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-info">Excused</span></td>
                                <td class="text-end"><?= e($excusedCount) ?></td>
                                <td class="text-end text-muted small"><?= $totalAttendance > 0 ? round(($excusedCount / $totalAttendance) * 100) : 0 ?>%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Incident Status -->
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Incident Status</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><span class="badge bg-secondary">Open</span></td>
                                <td class="text-end"><?= e($openIncidents) ?></td>
                                <td class="text-end text-muted small"><?= count($incidentsFiltered) > 0 ? round(($openIncidents / count($incidentsFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Investigating</span></td>
                                <td class="text-end"><?= e($investigatingIncidents) ?></td>
                                <td class="text-end text-muted small"><?= count($incidentsFiltered) > 0 ? round(($investigatingIncidents / count($incidentsFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Resolved</span></td>
                                <td class="text-end"><?= e($resolvedIncidents) ?></td>
                                <td class="text-end text-muted small"><?= count($incidentsFiltered) > 0 ? round(($resolvedIncidents / count($incidentsFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Offenders & Incident Types -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Top 5 Absent Students</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr><th>Student</th><th class="text-end">Absences</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($topOffenders)): ?>
                                    <?php foreach ($topOffenders as $sId => $count): ?>
                                        <?php $student = StudentService::find($sId); ?>
                                        <tr>
                                            <td><?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?></td>
                                            <td class="text-end"><span class="badge bg-danger"><?= e($count) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No data</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Incidents by Type</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr><th>Type</th><th class="text-end">Count</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($incidentsByType)): ?>
                                    <?php foreach (array_slice($incidentsByType, 0, 5) as $type => $count): ?>
                                        <tr>
                                            <td><?= e(ucfirst($type)) ?></td>
                                            <td class="text-end"><span class="badge bg-warning"><?= e($count) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No data</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitor & Medical Analytics -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Visitors by Relationship</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr><th>Relationship</th><th class="text-end">Count</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($visitorsByRelationship)): ?>
                                    <?php foreach (array_slice($visitorsByRelationship, 0, 5) as $rel => $count): ?>
                                        <tr>
                                            <td><?= e(ucfirst($rel)) ?></td>
                                            <td class="text-end"><span class="badge bg-info"><?= e($count) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No data</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Medical Case Summary</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><span class="badge bg-danger">Critical</span></td>
                                <td class="text-end"><?= e($criticalMedical) ?></td>
                                <td class="text-end text-muted small"><?= count($medicalFiltered) > 0 ? round(($criticalMedical / count($medicalFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Moderate</span></td>
                                <td class="text-end"><?= e($moderateMedical) ?></td>
                                <td class="text-end text-muted small"><?= count($medicalFiltered) > 0 ? round(($moderateMedical / count($medicalFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Normal</span></td>
                                <td class="text-end"><?= e(count($medicalFiltered) - $criticalMedical - $moderateMedical) ?></td>
                                <td class="text-end text-muted small"><?= count($medicalFiltered) > 0 ? round(((count($medicalFiltered) - $criticalMedical - $moderateMedical) / count($medicalFiltered)) * 100) : 0 ?>%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
