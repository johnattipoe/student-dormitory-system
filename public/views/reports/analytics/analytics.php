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

$fastApiToken = getenv('FASTAPI_API_TOKEN') ?: '';
$fastApiBaseUrl = rtrim(getenv('FASTAPI_BASE_URL') ?: 'https://student-dormitory-system.onrender.com', '/');

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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
$roleMetricOrder = [
    ROLE_ADMIN => ['attendance', 'incidents', 'visitors', 'medical'],
    ROLE_HOUSE_MASTER => ['attendance', 'incidents', 'visitors', 'medical'],
    ROLE_SENIOR_HOUSEPARENT => ['attendance', 'incidents', 'visitors', 'medical'],
    ROLE_NURSE => ['attendance', 'incidents', 'medical'],
    ROLE_SECURITY => ['attendance', 'incidents', 'visitors', 'rooms'],
    ROLE_STUDENT => ['attendance', 'medical'],
];
$visibleMetrics = $roleMetricOrder[$role] ?? $roleMetricOrder[ROLE_ADMIN];

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
    ['icon' => 'bi-bar-chart', 'label' => 'Analytics', 'href' => url('views/reports/analytics/analytics.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Analytics & Insights</h5>

        <!-- Date Range Filter -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/reports/analytics/analytics.php">
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
                    <a href="<?= url('views/reports/analytics/analytics.php') ?>" class="btn btn-outline-secondary btn-sm w-50">Reset</a>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="row g-3 mb-4">
            <?php if (in_array('attendance', $visibleMetrics, true)): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3" id="attendance-rate-card">
                    <div class="text-muted small">Attendance Rate</div>
                    <div class="fs-2 fw-bold text-success" id="attendance-rate-value"><?= e($avgAttendanceRate) ?>%</div>
                    <small class="text-muted" id="attendance-rate-meta"><?= e($presentCount) ?> present, <?= e($absentCount) ?> absent</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('incidents', $visibleMetrics, true)): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3" id="incident-card">
                    <div class="text-muted small">Incidents</div>
                    <div class="fs-2 fw-bold text-warning" id="incident-total"><?= e(count($incidentsFiltered)) ?></div>
                    <small class="text-muted" id="incident-meta"><?= e($openIncidents) ?> open, <?= e($highSeverityIncidents) ?> high severity</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('visitors', $visibleMetrics, true)): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3" id="visitor-card">
                    <div class="text-muted small">Visitor Approvals</div>
                    <div class="fs-2 fw-bold text-info" id="visitor-total"><?= e($approvedVisitors) ?></div>
                    <small class="text-muted" id="visitor-meta"><?= e($pendingVisitors) ?> pending, <?= e($rejectedVisitors) ?> rejected</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('medical', $visibleMetrics, true)): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3" id="medical-card">
                    <div class="text-muted small">Medical Cases</div>
                    <div class="fs-2 fw-bold text-danger" id="medical-total"><?= e(count($medicalFiltered)) ?></div>
                    <small class="text-muted" id="medical-meta"><?= e($criticalMedical) ?> critical, <?= e($moderateMedical) ?> moderate</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('rooms', $visibleMetrics, true)): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3" id="room-summary-card">
                    <div class="text-muted small">Room Occupancy</div>
                    <div class="fs-2 fw-bold text-primary" id="rooms-summary-value">0</div>
                    <small class="text-muted" id="rooms-summary-meta">0 occupied</small>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY], true)): ?>
        <div class="row g-3 mb-4">
            <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY], true)): ?>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Room Occupancy</h6>
                    <div id="room-occupancy-summary" class="small text-muted">Loading live room usage…</div>
                    <div id="room-occupancy-chart" class="mt-3"></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Incident Breakdown</h6>
                    <div id="incident-breakdown-chart" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_NURSE, ROLE_SECURITY], true)): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Attendance Status Trend</h6>
                    <div id="attendance-status-chart" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Incident Type Trend</h6>
                    <div id="incident-type-chart" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const apiToken = <?= json_encode($fastApiToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const fastApiBaseUrl = <?= json_encode($fastApiBaseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const summaryUrl = fastApiBaseUrl + '/analytics/summary';
        const roomsUrl = fastApiBaseUrl + '/analytics/rooms';
        const incidentsUrl = fastApiBaseUrl + '/analytics/incidents';

        function buildHeaders() {
            const headers = { Accept: 'application/json' };
            if (apiToken) {
                headers.Authorization = 'Bearer ' + apiToken;
            }
            return headers;
        }

        function renderBarChart(containerId, entries, colorClass) {
            const container = document.getElementById(containerId);
            if (!container) return;

            const max = Math.max(...entries.map(function (entry) { return Number(entry.value || 0); }), 1);
            container.innerHTML = entries.map(function (entry) {
                const width = (Number(entry.value || 0) / max) * 100;
                return '<div class="mb-2"><div class="d-flex justify-content-between small mb-1"><span class="text-capitalize">' + entry.label + '</span><span>' + entry.value + '</span></div><div class="progress" style="height: 10px;"><div class="progress-bar ' + colorClass + '" role="progressbar" style="width: ' + width + '%"></div></div></div>';
            }).join('');
        }

        fetch(summaryUrl, { headers: buildHeaders() })
            .then(function (response) {
                if (!response.ok) throw new Error('Live summary request failed');
                return response.json();
            })
            .then(function (data) {
                const attendanceRate = Number(data.attendanceRate ?? 0);
                const incidentTotal = Number(data.securityIncidents ?? 0);
                const visitorTotal = Number(data.totalVisitors ?? 0);
                const medicalTotal = Number(data.medicalAlerts ?? 0);
                const attendanceText = `${Number(data.attendance?.present ?? 0)} present, ${Number(data.attendance?.absent ?? 0)} absent`;

                const attendanceValue = document.getElementById('attendance-rate-value');
                const attendanceMeta = document.getElementById('attendance-rate-meta');
                const incidentValue = document.getElementById('incident-total');
                const incidentMeta = document.getElementById('incident-meta');
                const visitorValue = document.getElementById('visitor-total');
                const visitorMeta = document.getElementById('visitor-meta');
                const medicalValue = document.getElementById('medical-total');
                const medicalMeta = document.getElementById('medical-meta');

                if (attendanceValue) attendanceValue.textContent = `${attendanceRate.toFixed(0)}%`;
                if (attendanceMeta) attendanceMeta.textContent = attendanceText;
                if (incidentValue) incidentValue.textContent = String(incidentTotal);
                if (incidentMeta) incidentMeta.textContent = `${Number(data.activeExeatRequests ?? 0)} active exeats, ${Number(data.securityIncidents ?? 0)} incidents`;
                if (visitorValue) visitorValue.textContent = String(visitorTotal);
                if (visitorMeta) visitorMeta.textContent = `${Number(data.totalVisitors ?? 0)} total visitors`;
                if (medicalValue) medicalValue.textContent = String(medicalTotal);
                if (medicalMeta) medicalMeta.textContent = `${Number(data.medicalAlerts ?? 0)} urgent cases`;

                const attendanceEntries = [
                    { label: 'Present', value: Number(data.attendance?.present ?? 0) },
                    { label: 'Absent', value: Number(data.attendance?.absent ?? 0) },
                    { label: 'Late', value: Number(data.attendance?.late ?? 0) },
                    { label: 'Excused', value: Number(data.attendance?.excused ?? 0) },
                ];
                renderBarChart('attendance-status-chart', attendanceEntries, 'bg-success');

                const incidentTypeBreakdown = Object.entries(data.incidentBreakdown?.byType || {}).map(function ([label, value]) {
                    return { label: String(label), value: Number(value || 0) };
                }).slice(0, 5);
                renderBarChart('incident-type-chart', incidentTypeBreakdown.length ? incidentTypeBreakdown : [{ label: 'No data', value: 0 }], 'bg-warning');
            })
            .catch(function () {
                // Keep PHP-rendered values as the fallback when the FastAPI service is offline.
            });

        fetch(roomsUrl, { headers: buildHeaders() })
            .then(function (response) {
                if (!response.ok) throw new Error('Room occupancy request failed');
                return response.json();
            })
            .then(function (data) {
                const summary = document.getElementById('room-occupancy-summary');
                const chart = document.getElementById('room-occupancy-chart');
                const rooms = Array.isArray(data.rooms) ? data.rooms.slice(0, 5) : [];

                if (summary) {
                    summary.textContent = `${Number(data.occupiedBeds ?? 0)} occupied / ${Number(data.totalCapacity ?? 0)} capacity (${Number(data.utilizationRate ?? 0)}% utilized)`;
                }

                const roomSummaryValue = document.getElementById('rooms-summary-value');
                const roomSummaryMeta = document.getElementById('rooms-summary-meta');
                if (roomSummaryValue) roomSummaryValue.textContent = String(Number(data.totalCapacity ?? 0));
                if (roomSummaryMeta) roomSummaryMeta.textContent = `${Number(data.occupiedBeds ?? 0)} occupied`;

                if (chart) {
                    chart.innerHTML = rooms.map(function (room) {
                        const pct = room.capacity ? Math.min((Number(room.occupied || 0) / Number(room.capacity)) * 100, 100) : 0;
                        return '<div class="mb-2"><div class="d-flex justify-content-between small mb-1"><span>' + (room.id || 'Room') + '</span><span>' + Number(room.occupied || 0) + '/' + Number(room.capacity || 0) + '</span></div><div class="progress" style="height: 10px;"><div class="progress-bar bg-success" role="progressbar" style="width: ' + pct + '%"></div></div></div>';
                    }).join('') || '<div class="text-muted small">No room data available.</div>';
                }
            })
            .catch(function () {
                const summary = document.getElementById('room-occupancy-summary');
                if (summary) summary.textContent = 'Room usage data unavailable.';
            });

        fetch(incidentsUrl, { headers: buildHeaders() })
            .then(function (response) {
                if (!response.ok) throw new Error('Incident breakdown request failed');
                return response.json();
            })
            .then(function (data) {
                const container = document.getElementById('incident-breakdown-chart');
                if (!container) return;

                const breakdown = Object.entries(data.byType || {}).slice(0, 5);
                if (!breakdown.length) {
                    container.innerHTML = '<div class="text-muted small">No incident data.</div>';
                    return;
                }

                const maxCount = Math.max(...breakdown.map(function (entry) { return Number(entry[1] || 0); }), 1);
                container.innerHTML = breakdown.map(function ([type, count]) {
                    const width = (Number(count || 0) / maxCount) * 100;
                    return '<div><div class="d-flex justify-content-between small text-capitalize mb-1"><span>' + type + '</span><span>' + count + '</span></div><div class="progress" style="height: 10px;"><div class="progress-bar bg-warning" role="progressbar" style="width: ' + width + '%"></div></div></div>';
                }).join('');
            })
            .catch(function () {
                const container = document.getElementById('incident-breakdown-chart');
                if (container) container.innerHTML = '<div class="text-muted small">Incident data unavailable.</div>';
            });
    });
</script>
